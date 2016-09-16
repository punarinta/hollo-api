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

        $sql = 'SELECT m.*, u.id AS u_id, u.name AS u_name, u.email AS u_email FROM message AS m LEFT JOIN `user` AS u ON u.id=m.user_id WHERE chat_id = ?';
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
     * @param $user         -- current user
     * @return array
     */
    public function moreByChat($chat, $user)
    {
        $offset = 0;
        $synced = 0;
        $limit = 100;

        $oldCount = $this->countByChatId($chat->id);

        // get more messages for this chat starting from chat's last sync
        foreach (\Sys::svc('User')->findByChatId($chat->id) as $participant)
        {
            $params =
            [
                'limit'         => $limit,
                'date_after'    => abs($chat->last_ts - 86400),
                'sort_order'    => 'desc',
                'email'         => $participant->email,
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
                        $rows = $data->getData();

                        foreach ($rows as $row)
                        {
                            // fetch even muted, as the user has explicitly instructed us
                            $synced += 1 * $this->processMessageSync($user, $row, true, $chat->id);
                        }

                        if ($synced >= \Sys::cfg('sys.sync_depth'))
                        {
                            goto enough;
                        }

                        break;
                    }
                    else
                    {
                        $this->retryVerbose($params, ++$attempt);
                    }
                }

                if (count($rows) < $limit)
                {
                    break;
                }

                $offset += $limit;

                // sleep a bit to prevent API request queue growth
                usleep(600000);
            }
        }

        enough:;

        // return with offset from DB
        $messages = $this->findByChatId($chat->id);

        $count = count($messages) - $oldCount;

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
            'limit'         => $limit,
            'date_after'    => ($user->last_sync_ts && !$fetchAll) ? abs($user->last_sync_ts - 86400) : 1,
            'sort_order'    => 'desc',
        ];

        while (1)
        {
            $rows = [];
            $attempt = 0;
            $params['offset'] = $offset;

            echo "User {$user->email}, offset = $offset\n";

            while ($attempt < 10)
            {
                if ($data = $this->conn->listMessages($user->ext_id, $params))
                {
                    $rows = $data->getData();

                    foreach ($rows as $row)
                    {
                        $this->processMessageSync($user, $row, $fetchMuted);
                    }

                    break;
                }
                else
                {
                    $this->retryVerbose($params, ++$attempt);
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
     * @param bool $verbose
     * @return bool|\StdClass
     * @throws \Exception
     */
    public function sync($accountId, $messageExtId, $verbose = false)
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

        $data = $data->getData();

        if ($verbose)
        {
            print_r($data);
        }

        return $this->processMessageSync($user, $data);
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
        if (!$message = $this->findById($messageId))
        {
            throw new \Exception('Message does not exist');
        }

        // '571f73e9920d7f18098b4567'
        if (!$data = $this->getDataByExtId(\Auth::user()->ext_id, $message->ext_id))
        {
            return false;
        }

        if (!isset ($data['body'][$bodyId]['content']))
        {
            throw new \Exception('Body does not exist');
        }

        // echo replace4byte($data['body'][$bodyId]['content']);

        $message->body = $this->clearContent($data['body'][$bodyId]['type'], $data['body'][$bodyId]['content']);
        $this->update($message);

        return true;
    }

    /**
     * Gets message data from external provider
     *
     * @param $userExtId
     * @param $extId
     * @param bool $retry
     * @return null
     */
    public function getDataByExtId($userExtId, $extId, $retry = true)
    {
        $attempt = 0;
        $params = ['message_id' => $extId, 'include_body' => 1];

        while ($attempt < 10)
        {
            if ($data = $this->conn->getMessage($userExtId, $params))
            {
                return $data->getData();
            }
            elseif ($retry)
            {
                $this->retryVerbose($params, ++$attempt);
            }
            else break;
        }

        return null;
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

    /**
     * @param $message
     * @return bool
     */
    public function reClean($message)
    {
        $oldLen = mb_strlen($message->body);
        $newStr = $this->clearContent(null, $message->body);

        if ($oldLen > mb_strlen($newStr))
        {
            $message->body = $newStr;
            $this->update($message);
            return true;
        }

        return false;
    }

    // ============================
    // ===  internal functions  ===
    // ============================

    /**
     * @param $user                 -- recipient user
     * @param $messageData
     * @param bool $fetchMuted
     * @param int $limitToChatId
     * @return bool|\StdClass
     * @throws \Exception
     */
    protected function processMessageSync($user, $messageData, $fetchMuted = false, $limitToChatId = 0)
    {
        // message must not exist
        $extId = $messageData['message_id'];

        if (!$message = $this->findByExtId($extId))
        {
            // collect emails and names from the message
            $emails = [$messageData['addresses']['from']['email']];
            $names = [$messageData['addresses']['from']['email'] => @$messageData['addresses']['from']['name']];

            if (isset ($messageData['addresses']['to'])) foreach ($messageData['addresses']['to'] as $to)
            {
                $emails[] = $to['email'];
                $names[$to['email']] = @$to['name'];
            }

            if (isset ($messageData['addresses']['cc'])) foreach ($messageData['addresses']['cc'] as $cc)
            {
                $emails[] = $cc['email'];
                $names[$cc['email']] = @$cc['name'];
            }

            // we don't need duplicates
            $emails = array_unique($emails);

            // chat may not exist -> init and mute if necessary
            if (!$chat = \Sys::svc('Chat')->findByEmails($emails))
            {
                if ($limitToChatId)
                {
                    // chat does not exist while ID restriction is imposed -> leave
                    return false;
                }

                if (count($emails) < 2)
                {
                    // the cannot be less than 2 people in chat
                    return false;
                }

                $chat = \Sys::svc('Chat')->init($emails, [$user->id], $names);
            }
            else
            {
                if ($limitToChatId && $chat->id != $limitToChatId)
                {
                    // chat exists, but does not fulfill the ID requirement, used by moreByChatId
                    return false;
                }
            }

            if (!$chat)
            {
                throw new \Exception('Chat does not exist');
            }

            if (!$fetchMuted /*&& count($emails) == 2*/)
            {
                // check that you want any messages in this chat
                // message sync on behalf of a bot will not happen

                $recipient = \Sys::svc('User')->findByEmail(@$messageData['addresses']['to'][0]['email']);
                $flags = \Sys::svc('Chat')->getFlags($chat->id, @$recipient->id);

                if ($flags && $flags->muted)
                {
                    return false;
                }
            }

            // assure the message is not a draft
            if (isset ($messageData['folders'])) foreach ($messageData['folders'] as $folder)
            {
                if (stripos($folder, 'draft') !== false)
                {
                    return false;
                }
            }

            // check after muting is tested
            if (!isset ($messageData['body']))
            {
                echo "Body extraction. Ext ID = {$extId}\n";
                $messageData = $this->getDataByExtId($user->ext_id, $extId);
            }


            // === process the message content ===

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

            if (!isset ($messageData['body'][0]) || !strlen($messageData['body'][0]['content']))
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


            // avoid duplicating by user_id-chat_id-ts key
            if (!$this->isUnique($senderId, $chat->id, $messageData['date']))
            {
                return false;
            }

            $content = $this->clearContent($messageData['body'][0]['type'], $messageData['body'][0]['content']);

            // check for forwarded messages
            if (stripos($messageData['subject'], 'FWD:', 0) === 0)
            {
                $content .= "[sys:fwd]";
            }

            if (!mb_strlen($content) && empty ($files))
            {
                // avoid empty replies
                return false;
            }

            $message = $this->create(array
            (
                'ext_id'    => $extId,
                'user_id'   => $senderId,
                'chat_id'   => $chat->id,
                'subject'   => mb_convert_encoding($this->clearSubject($messageData['subject']), 'UTF-8'),
                'body'      => mb_convert_encoding($content, 'UTF-8'),
                'files'     => empty ($files) ? '' : json_encode($files),
                'ts'        => $messageData['date'],
             ));

            // message received, update chat if it's a new one
            $chat->last_ts = max($messageData['date'], $chat->last_ts);
            \Sys::svc('Chat')->update($chat);

            // there were one or more new messages -> reset 'read' flag
            \Sys::svc('Chat')->setReadFlag($chat->id, $user->id, 0);
        }

        return $message;
    }

    /**
     * Assures there are no duplicates by user-chat-ts
     *
     * @param $userId
     * @param $chatId
     * @param $ts
     * @return bool
     */
    protected function isUnique($userId, $chatId, $ts)
    {
        return !\DB::row('SELECT id FROM message WHERE user_id=? AND chat_id=? AND ts=? LIMIT 1', [$userId, $chatId, $ts]);
    }

    protected function clearSubject($subject)
    {
        $items = ['RE','FWD','FW','VS','VB','SV'];

        // remove things in brackets
        $subject = trim(preg_replace('/\[[^]]+\]/', '', $subject));

        foreach ($items as $item)
        {
            $item .= ':';
            if (stripos($subject, $item, 0) === 0)
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

        $content = str_replace("\r\n", "\n", $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        $quoteHeadersRegex = array
        (
            '/^([^\n]*On).*(wrote:).*$/sm',
            '/^([^\n]*Den).*(skrev:).*$/sm',
            '/^[^\n]*(<.+@.+>).*(skrev:).*$/sm',
            '/^[^\n]*(skrev).*(<.+@.+>:).*$/sm',
        );

        // Remove lines like '--- On ... wrote:' (some other clients).
        foreach ($quoteHeadersRegex as $regex)
        {
            $content = preg_replace($regex, '', $content);
        }

        // Remove quoted lines (lines that begin with '>') and 1 line before that
        $content = preg_replace("/(^\w.+:\n+)?(^>.*(\n|$)){2,}/mi", '', $content);

        // remove separators containing 10 or more dashes or equals (dirty position) and everything below
        $content = preg_replace("/^[^\n]*(-{5,}|={5,}).*$/ms", '', $content);

        // signatures starting with '--' in a lone position
        $content = preg_replace("/^-{2}\s*$.*/ms", '', $content);

        // Remove lines like '____________'
        $content = preg_replace("/^____________.*$/mi", '', $content);

        // Remove blocks of text with formats like:
        //   - 'From: Sent: To: Subject:'
        //   - 'From: To: Sent: Subject:'
        //   - 'From: Date: To: Reply-to: Subject:'
        $langs = array
        (
            'en' => ['from' => 'From', 'to' => 'To', 'subject' => 'Subject', 'sent' => 'Sent'],
            'sv' => ['from' => 'Från', 'to' => 'Till', 'subject' => 'Ämne', 'sent' => 'Skickat'],
            'no' => ['from' => 'Fra', 'to' => 'Til', 'subject' => 'Emne', 'sent' => 'Sendt'],
        );

        foreach ($langs as $lang)
        {
            $quoteHeadersRegex = array
            (
                '/' . $lang['from'] . ':.*^(' . $lang['to'] . ':).*^(' . $lang['subject'] . ':).*/sm',
                '/' . $lang['from'] . ':.*^(' . $lang['sent'] . ':).*^(' . $lang['to'] . ':).*/sm',
                '/' . $lang['from'] . ':.*^(' . $lang['subject'] . ':).*^(' . $lang['to'] . ':).*/sm',
                '/' . $lang['subject'] . ':.*^(' . $lang['from'] . ':).*^(' . $lang['to'] . ':).*/sm',
                '/\*' . $lang['from'] . ':\*.*^(\*' . $lang['to'] . ':\*).*^(\*' . $lang['subject'] . ':\*).*/sm',
                '/\*' . $lang['from'] . ':\*.*^(\*' . $lang['sent'] . ':\*).*^(\*' . $lang['to'] . ':\*).*/sm',
                '/\*' . $lang['from'] . ':\*.*^(\*' . $lang['subject'] . ':\*).*^(\*' . $lang['to'] . ':\*).*/sm',
            );
            foreach ($quoteHeadersRegex as $regex)
            {
                $content = preg_replace($regex, '', $content);
            }
        }

        // remove inline images
        $content = preg_replace('/\[image:[^]]+\]/', '', $content);
        $content = preg_replace('/\[cid:[^]]+\]/', '', $content);
        $content = preg_replace('/\[inline image\]/i', '', $content);

        // remove zero-width space
        $content = str_replace("\xE2\x80\x8B", '', $content);

        return trim($content);
    }

    /**
     * A service function to report and wait
     *
     * @param $params
     * @param $attempt
     */
    protected function retryVerbose($params, $attempt)
    {
        $timeout = (int)(5 + $attempt/3);
        echo "Sync error, attempt #$attempt in $timeout seconds\n";
        sleep($timeout);
        print_r($params);
    }
}
