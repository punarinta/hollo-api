<?php

namespace App\Service;

class Message extends Generic
{
    /**
     * Finds a Message by its external ID
     *
     * @param $extId
     * @return null|\StdClass
     */
    public function findByExtId($extId)
    {
        return \DB::row('SELECT * FROM message WHERE ext_id = ? LIMIT 1', [$extId]);
    }

    /**
     * Returns the total count of messages synced for this contact and user
     *
     * @param $email
     * @param $userId
     * @return mixed
     */
    public function countByContactAndUserId($email, $userId)
    {
        $x = \DB::row('SELECT count(cm.message_id) AS c FROM contact_message AS cm LEFT JOIN contact AS c ON c.id=cm.contact_id WHERE c.email = ? AND c.user_id=?', [$email, $userId]);
        return $x->c;
    }

    /**
     * Returns messages associated with a contact. May filter by subject.
     *
     * @param $contactId
     * @param null $subject
     * @param int $offset
     * @return array
     */
    public function findByContactId($contactId, $subject = null, $offset = 0)
    {
        $items = [];

        $sql =
            "SELECT m.*, c2.id AS c2_id, c2.name AS c2_name, c2.email AS c2_email
             FROM message AS m 
             LEFT JOIN contact_message AS cm ON m.id=cm.message_id
             LEFT JOIN contact AS c ON c.id=cm.contact_id
             LEFT JOIN contact AS c2 ON c2.id=m.from_contact_id
             WHERE c.id = ?";
        $params = [$contactId];

        if ($subject)
        {
            $sql .= ' AND m.subject LIKE ?';
            $params[] = '%' . $subject . '%';
        }

        $sql .= ' ORDER BY ts';

        if ($offset)
        {
            $sql .= ' LIMIT=100 OFFSET=?';      // Anyway, 100 is a limit in Context.IO
            $params[] = $offset;
        }

        foreach (\DB::rows($sql, $params) as $item)
        {
            $items[] = \DB::toObject(array
            (
                'id'        => $item->id,
                'ts'        => $item->ts,
                'body'      => $item->body,
                'subject'   => $this->clearSubject($item->subject),
                'from'      => array
                (
                    'id'    => $item->c2_id,
                    'email' => $item->c2_email,
                    'name'  => $item->c2_name,
                ),
                'files'     => json_decode($item->files, true),
            ));
        }

        return $items;
    }

    /**
     * Fetches new messages, saves them to DB and returns in a normalized view
     *
     * @param $contact
     * @param $user
     * @return array
     */
    public function moreByContact($contact, $user)
    {
        // count the total amount and sync next N
        $total = $this->countByContact($contact->email, $user->id);

        // sync, even muted ones
        $this->syncAll($user, $contact, $total, true);

        // return with offset from DB
        return $this->findByContactId($contact->id, null, $total);
    }

    /**
     * Performs initial message sync for a specified contact
     *
     * @param $user
     * @param $contact
     * @param int $offset
     * @param bool $fetchAll
     * @return mixed
     */
    public function syncAll($user, $contact, $offset = 0, $fetchAll = false)
    {
        // don't sync your own message to yourself
        if ($contact->email == $user->email) return 0;

        $params =
        [
            'include_body'  => 1,
            'limit'         => \Sys::cfg('sys.sync_depth'),     // max limit = 100
            'email'         => $contact->email,
            'date_after'    => $user->last_sync_ts ? abs($user->last_sync_ts - 86400) : 1,
            'sort_order'    => 'desc',
            'offset'        => $offset,
        ];

        $attempt = 0;

        while ($attempt < 10)
        {
            if ($data = $this->conn->listMessages($user->ext_id, $params))
            {
                // TODO: here a connection drop was once detected
                $rows = $data->getData();

                foreach ($rows as $row)
                {
                    $this->processMessageSync($user, $row, $contact, $fetchAll);
                }

                return count($rows);
            }
            else
            {
                ++$attempt;
                echo "Error syncing '{$contact->email}', attempt #$attempt in 5 seconds\n";
                sleep(5);
                print_r($params);
            }
        }

        return 0;
    }

    /**
     * Sync a single message
     *
     * @param $accountId
     * @param $messageExtId
     * @return bool
     * @throws \Exception
     */
    public function sync($accountId, $messageExtId)
    {
        // temporary fall back to sync all
        if (!$data = $this->conn->getMessage($accountId, ['message_id' => $messageExtId, 'include_body' => 1]))
        {
            return false;
        }

        if (!$user = \Sys::svc('User')->findByExtId($accountId))
        {
            throw new \Exception('User does not exist.');
        }

        $data = $data->getData();

        if (!$contact = \Sys::svc('Contact')->findByEmailAndAccountId($data['addresses']['from']['email'], $accountId))
        {
            if ($data['addresses']['from']['email'] == $user->email)
            {
                // don't sync your own message to yourself
                return false;
            }

            // no contact exist -> create it
            $contact = \Sys::svc('Contact')->create(array
            (
                'user_id'   => $user->id,
                'email'     => $data['addresses']['from']['email'],
                'name'      => $data['addresses']['from']['name'],
                'count'     => 1,
                'muted'     => 0,
                'read'      => 0,
                'last_ts'   => $data['date'],
            ));
        }
        else
        {
            // update contact's count
            $contactData = $this->conn->getContact($accountId, ['email' => $contact->email])->getData();
            $contact->count = $contactData['count'];
        }

        if ($this->processMessageSync($user, $data, $contact))
        {
            // there was a new message
            $contact->read = 0;
            $contact->last_ts = $data['date'];
            \Sys::svc('Contact')->update($contact);
        }

        return true;
    }

    /**
     * Switches message body and updates it in the database
     *
     * @param $messageId
     * @param $bodyId
     * @return bool
     * @throws \Exception
     */
    public function switchBody($messageId, $bodyId)
    {
        if (!$message = \Sys::svc('Message')->findById($messageId))
        {
            throw new \Exception('Message does not exist');
        }

        if (!$data = $this->conn->getMessage(\Auth::user()->id, ['message_id' => $message->ext_id, 'include_body' => 1]))
        {
            return false;
        }

        if (!isset ($data['body'][$bodyId]['content']))
        {
            throw new \Exception('Body does not exist');
        }

        $message->body = $this->clearContent($data['body'][$bodyId]['type'], $data['body'][$bodyId]['content'], $data['addresses']['from']['email']);
        \Sys::svc('Message')->update($message);

        return true;
    }

    /**
     * Removes excessive messages
     *
     * @param $contactId
     * @return int
     * @throws \Exception
     */
    public function removeOld($contactId)
    {
        $messages = $this->findByContactId($contactId, null, 0);
        $messages = array_slice($messages, \Sys::cfg('sys.sync_depth'));

        foreach ($messages as $message)
        {
            // remove message link for this contact
            $stmt = \DB::prepare('DELETE FROM contact_message WHERE message_id=? AND contact_id=?', [$message->id, $contactId]);
            $stmt->execute();
            $stmt->close();

            // if the message became orphan, remove it too
            $x = \DB::row('SELECT count(0) AS c FROM contact_message WHERE message_id=?', [$message->id]);
            if (!$x->c)
            {
                $this->delete($message);
            }
        }

        return count($messages);
    }

    // === internal functions ===

    /**
     * @param $user
     * @param $messageData
     * @param $contact
     * @param bool $fetchAll
     * @return bool
     * @throws \Exception
     */
    protected function processMessageSync($user, $messageData, $contact, $fetchAll = false)
    {
        // check if muted
        if (!$fetchAll && $contact->muted)
        {
            return false;
        }

        // check if message is present and sync if necessary
        $extId = $messageData['message_id'];

        if (!$message = \Sys::svc('Message')->findByExtId($extId))
        {
            $files = [];

            if (isset ($messageData['files']))
            {
                foreach ($messageData['files'] as $file)
                {
                    $files[] = array
                    (
                        'name'  => $file['file_name'],
                        'type'  => $file['type'],
                        'size'  => $file['size'],
                        'extId' => $file['file_id'],
                    );
                }
            }

            if (!isset ($messageData['body'][0]))
            {
                // no body?
                return false;
            }

            if ($messageData['addresses']['from']['email'] == $user->email) $senderId = 0;
            else
            {
                $sender = \Sys::svc('Contact')->findByEmailAndUserId($messageData['addresses']['from']['email'], $contact->user_id);
                $senderId = $sender->id;
            }

            $message = \Sys::svc('Message')->create(array
            (
                'ts'                => $messageData['date'],
                'body'              => $this->clearContent($messageData['body'][0]['type'], $messageData['body'][0]['content'], $contact->email),
                'from_contact_id'   => $senderId,
                'subject'           => $messageData['subject'],
                'ext_id'            => $extId,
                'files'             => empty($files) ? '' : json_encode($files),
            ));
        }

        \DB::prepare('REPLACE INTO contact_message (contact_id, message_id) VALUES (?,?)', [$contact->id, $message->id])->execute();

        return true;
    }

    protected function clearSubject($subject)
    {
        $items = ['Re:', 'Fwd:', 'Fw:'];

        foreach ($items as $item)
        {
            if (strpos($subject, $item, 0) === 0)
            {
                $subject = str_ireplace($item, '', $subject);
                break;
            }
        }

        return trim($subject);
    }

    /**
     * @param $type
     * @param $content
     * @param $email        — sender
     * @return null
     */
    protected function clearContent($type, $content, $email)
    {
        /*

        samples:

        -- Please reply above this line --

         */

        if ($type == 'text/html')
        {
            // do not let HTML spam in
            if (substr($content, 0, 1) == '<')
            {
                // use prelim check to speedup
                if (substr($content, 0, 5) == '<html') return null;
                if (substr($content, 0, 5) == '<meta') return null;
                if (substr($content, 0, 5) == '<?xml') return null;
                if (substr($content, 0, 5) == '<!-- ') return null;
            }
            if (strpos($content, '<table') !== false) return null;
            if (strpos($content, '<!DOCTYPE html') !== false) return null;

            $content = preg_replace('/<blockquote(.*)<\/blockquote>/im', '', $content);
            $content = str_replace('</div><div>', "\n", $content);
            $content = str_ireplace(['<br />','<br>','<br/>'], "\n", $content);
            $content = strip_tags($content);
            $content = html_entity_decode($content);
        }


        // remove email
        $email = str_replace('.', '\.', $email);
        if (preg_match("/^.*(<$email\s*>).*$/s", $content, $matches))
        {
            $content = str_replace($matches[1], '', $content);
        }

        // Remove quoted lines (lines that begin with '>').
        $content = preg_replace("/(^\w.+:\n)?(^>.*(\n|$))+/mi", '', $content);

        $quoteHeadersRegex = array
        (
            '/^(On\s.+?wrote:).*$/ms',
            '/^(Den\s.+?skrev:).*$/ms',
        );

        foreach ($quoteHeadersRegex as $regex)
        {
            $content = preg_replace($regex, '', $content);
        }

        // Remove lines like '----- Original Message -----' (some other clients).
        // Also remove lines like '--- On ... wrote:' (some other clients).
        $content = preg_replace("/^---.*$/mi", '', $content);

        // remove zero-width space
        $content = str_replace("\xE2\x80\x8B", '', $content);

        // most probably that was HTML
        if (!strlen(trim($content))) return null;

        return trim($content);
    }
}
