<?php

namespace App\Service;

class Message extends Generic
{
    /**
     * @param $extId
     * @return null|\StdClass
     */
    public function findByExtId($extId)
    {
        return \DB::row('SELECT * FROM message WHERE ext_id = ? LIMIT 1', [$extId]);
    }

    /**
     * Returns messages associated with a contact. May filter by subject.
     *
     * @param $email
     * @param null $subject
     * @return array
     */
    public function findByContact($email, $subject = null)
    {
        $items = [];

        $sql = 'SELECT * FROM message AS m LEFT JOIN contact_message AS cm ON m.id=cm.message_id LEFT JOIN contact AS c ON c.id=cm.contact_id WHERE c.email = ?';
        $params = [$email];

        if ($subject)
        {
            $sql .= ' AND m.subject LIKE ?';
            $params[] = '%' . $subject . '%';
        }

        $sql .= ' ORDER BY ts';

        foreach (\DB::rows($sql, $params) as $item)
        {
            $items[] = array
            (
                'ts'        => $item->ts,
                'body'      => $item->body,
                'subject'   => $this->clearSubject($item->subject),
                'from'      => $item->sender,
                'files'     => json_decode($item->files, true),
            );
        }

        return $items;
    }

    /**
     * Performs message sync for a specified email
     *
     * @param $user
     * @param $contact
     * @return int
     */
    public function syncAll($user, $contact)
    {
        $count = 0;
        $limit = 100;
        $offset = 0;

        $params =
        [
            'include_body'  => 1,
            'limit'         => $limit,
            'email'         => $contact->email,
            'date_after'    => $user->last_sync_ts ?: 1,
        ];

        // paginate requests
        while (1)
        {
            $params['offset'] = $offset;

            $rows = $this->conn->listMessages($user->ext_id, $params)->getData();

            foreach ($rows as $row)
            {
                $this->processMessageSync($row, $contact);
            }

            $count += count($rows);
            
            if (count($rows) < $limit)
            {
                break;
            }

            $offset += $limit;
        }

        return $count;
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

        $data = $data->getData();

        if (!$contact = \Sys::svc('Contact')->findByEmailAndAccountId($data['addresses']['from']['email'], $accountId))
        {
            // no contact exist, create it
            if (!$user = \Sys::svc('User')->findByExtId($accountId))
            {
                throw new \Exception('User does not exist.');
            }

            $contact = \Sys::svc('Contact')->create(array
            (
                'user_id'   => $user->id,
                'email'     => $data['addresses']['from']['email'],
                'name'      => $data['addresses']['from']['name'],
                'count'     => 1,
            ));
        }

        $this->processMessageSync($data, $contact);

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

        $message->body = $this->clearContent($data['body'][$bodyId]['content'], $data['addresses']['from']['email']);
        \Sys::svc('Message')->update($message);

        return true;
    }

    // === internal functions ===

    protected function processMessageSync($messageData, $contact)
    {
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
                return;
            }

            $message = \Sys::svc('Message')->create(array
            (
                'ts'            => $messageData['date'],
                'body'          => $this->clearContent($messageData['body'][0]['content'], $contact->email),
                'sender'        => $messageData['addresses']['from']['email'],
                'subject'       => $messageData['subject'],
                'ext_id'        => $extId,
                'files'         => empty($files) ? '' : json_encode($files),
            ));
        }

        \DB::prepare('REPLACE INTO contact_message (contact_id, message_id) VALUES (?,?)', [$contact->id, $message->id])->execute();
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
     * @param $content
     * @param $email        — sender
     * @return mixed
     */
    protected function clearContent($content, $email)
    {
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

        return trim($content);
    }
}
