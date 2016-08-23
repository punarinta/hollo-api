<?php

namespace App\Service;

class Message extends Generic
{
    /**
     * Returns the last message from the chat
     *
     * @param $chatId
     * @return null|\StdClass
     */
    public function getLastByChatId($chatId)
    {
        return \DB::row('SELECT * FROM message WHERE chat_id = ? ORDER BY ts DESC LIMIT 1', [$chatId]);
    }

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
     * Returns the total count of messages synced for this Chat
     *
     * @param $chatId
     * @return mixed
     */
    public function countByChatId($chatId)
    {
        $x = \DB::row('SELECT count(0) AS c FROM message WHERE chat_id=?', [$chatId]);

        return $x->c;
    }

    /**
     * Returns messages associated with a chat. May filter by subject.
     *
     * @param $contactId
     * @param null $subject
     * @param int $offset
     * @return array
     */
    public function findByChatId($contactId, $subject = null, $offset = 0)
    {
        $items = [];

        $sql = "SELECT m.*, u.id AS u_id, u.name AS u_name, u.email AS u_email
             FROM message AS m 
             LEFT JOIN `user` AS u ON u.id=m.user_id
             WHERE chat_id = ?";
        $params = [$contactId];

        if ($subject)
        {
            $sql .= ' AND m.subject LIKE ?';
            $params[] = '%' . $subject . '%';
        }

        $sql .= ' ORDER BY ts ASC';

        if ($offset)
        {
            $sql .= ' LIMIT 100 OFFSET ?';      // Anyway, 100 is a limit in Context.IO
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
                    'id'    => $item->u_id,
                    'email' => $item->u_email,
                    'name'  => $item->u_name,
                ),
                'files'     => json_decode($item->files, true),
            ));
        }

        return $items;
    }

    /**
     * Gets more messages into the Chat
     *
     * @param $chat
     * @return array
     */
    public function moreByChat($chat)
    {
        // count the total amount and sync next N
        $total = $this->countByChatId($chat->id);

        // get more messages for this chat starting from $total

        // TODO: above

        // return with offset from DB
        $messages = $this->findByChatId($chat->id);

        $count = count($messages) - $total;

        return $count > 0 ? array_slice($messages, 0, $count) : [];
    }

    /**
     * Syncs all messages for the User
     *
     * @param $userId
     * @param bool $fetchMuted      - fetch muted too
     * @param bool $fetchAll        - fetch all messages for that user
     * @return int
     * @throws \Exception
     */
    public function syncAllByUserId($userId, $fetchMuted = false, $fetchAll = false)
    {
        $offset = 0;
        $limit = 100;

        if (!$user = \Sys::svc('User')->findById($userId))
        {
            throw new \Exception('User does not exist');
        }

        $params =
        [
            'include_body'  => 1,
            'limit'         => $limit,
            'date_after'    => ($user->last_sync_ts && !$fetchAll) ? abs($user->last_sync_ts - 86400) : 1,
            'sort_order'    => 'desc',
        ];

        while (1)
        {
            $rows = [];
            $attempt = 0;
            $params['offset'] = $offset;

            while ($attempt < 10)
            {
                if ($data = $this->conn->listMessages($user->ext_id, $params))
                {
                    // TODO: here a connection drop was once detected
                    $rows = $data->getData();

                    foreach ($rows as $row)
                    {
                        $this->processMessageSync($user, $row, $fetchMuted);
                    }

                    break;
                }
                else
                {
                    ++$attempt;
                    echo "Sync error, attempt #$attempt in 5 seconds\n";
                    sleep(5);
                    print_r($params);
                }
            }

            if (count($rows) < $limit)
            {
                return $offset + count($rows);
            }

            $offset += $limit;

            // sleep a bit to prevent API request queue growth
            usleep(600000);
        }

        return 0;
    }

    /**
     * Syncs a single message
     *
     * @param $accountId
     * @param $messageExtId
     * @return bool
     * @throws \Exception
     */
    public function sync($accountId, $messageExtId)
    {
        if (!$data = $this->conn->getMessage($accountId, ['message_id' => $messageExtId, 'include_body' => 1]))
        {
            // no data -> skip this sync
            return false;
        }

        if (!$user = \Sys::svc('User')->findByExtId($accountId))
        {
            throw new \Exception('User does not exist.');
        }

        $this->processMessageSync($user, $data->getData());

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

        // '571f73e9920d7f18098b4567'
        if (!$data = $this->conn->getMessage(\Auth::user()->ext_id, ['message_id' => $message->ext_id, 'include_body' => 1]))
        {
            return false;
        }

        $data = $data->getData();

        if (!isset ($data['body'][$bodyId]['content']))
        {
            throw new \Exception('Body does not exist');
        }

        // echo replace4byte($data['body'][$bodyId]['content']);

        $message->body = $this->clearContent($data['body'][$bodyId]['type'], $data['body'][$bodyId]['content']);
        \Sys::svc('Message')->update($message);

        return true;
    }

    /**
     * Removes excessive messages from a Chat
     *
     * @param $chatId
     * @return int
     * @throws \Exception
     */
    public function removeOld($chatId)
    {
        $messages = $this->findByChatId($chatId);
        $messages = array_reverse($messages);
        $messages = array_slice($messages, \Sys::cfg('sys.sync_depth'));

        foreach ($messages as $message)
        {
            $this->delete($message);
        }

        return count($messages);
    }

    // ============================
    // ===  internal functions  ===
    // ============================

    /**
     * @param $user                 -- recipient user
     * @param $messageData
     * @param bool $fetchMuted
     * @return bool
     * @throws \Exception
     */
    protected function processMessageSync($user, $messageData, $fetchMuted = false)
    {
        if (!$fetchMuted)
        {
            // TODO: check if this is a 2-person chat with one of the participants being muted
        }

        // message must not exist
        $extId = $messageData['message_id'];

        if (!\Sys::svc('Message')->findByExtId($extId))
        {
            // collect emails from the message
            $emails = [$messageData['addresses']['from']['email']];
            foreach ($messageData['addresses']['to'] as $to) $emails = $to['email'];
            foreach ($messageData['addresses']['cc'] as $cc) $emails = $cc['email'];

            // we don't need duplicates
            $emails = array_unique($emails);

            // chat may not exist -> init and mute if necessary
            if (!$chat = \Sys::svc('Chat')->findByEmails($emails))
            {
                $chat = \Sys::svc('Chat')->init($emails, [$user->id]);
            }

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

            if ($messageData['addresses']['from']['email'] == $user->email)
            {
                // the head user is the author
                $senderId = $user->id;
            }
            else
            {
                $sender = \Sys::svc('User')->findByEmail($messageData['addresses']['from']['email']);
                $senderId = $sender->id;
            }

            \Sys::svc('Message')->create(array
            (
                'ext_id'    => $extId,
                'user_id'   => $senderId,
                'chat_id'   => $chat->id,
                'subject'   => iconv('UTF-8', 'ISO-8859-1', $messageData['subject']),
                'body'      => iconv('UTF-8', 'ISO-8859-1', $this->clearContent($messageData['body'][0]['type'], $messageData['body'][0]['content'])),
                'files'     => empty ($files) ? '' : json_encode($files),
                'ts'        => $messageData['date'],
             ));

            // message received, update chat
            $chat->last_ts = $messageData['date'];
            \Sys::svc('Contact')->update($chat);

            // there were one or more new messages -> reset 'read' flag
            \Sys::svc('Chat')->setReadFlag($chat->id, $user->id, 0);
        }

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
     * @return null
     */
    protected function clearContent($type, $content)
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

        // Remove quoted lines (lines that begin with '>') and 1 line before that
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
