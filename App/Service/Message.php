<?php

namespace App\Service;

use App\Model\FileParser\Calendar;
use App\Model\Inbox\Inbox;

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
     * @param $refUserId
     * @param $extId
     * @return null|\StdClass
     */
    public function findByRefIdExtId($refUserId, $extId)
    {
        return \DB::row('SELECT * FROM message WHERE ref_id=? AND ext_id=? LIMIT 1', [$refUserId, $extId]);
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
                'refId'     => $item->ref_id,
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
     * Find last message in chat that is not
     *
     * @param $chatId
     * @return null|\StdClass
     */
    public function findByLastRealByChatId($chatId)
    {
        return \DB::row("SELECT * FROM message WHERE chat_id = ? AND ext_id != '' ORDER BY ts DESC LIMIT 1", [$chatId]);
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
        // TODO: t.b.d.
        return [];

        $offset = 0;
        $synced = 0;
        $limit = 20;

        $oldCount = $this->countByChatId($chat->id);

        // get more messages for this chat starting from chat's last sync
        foreach (\Sys::svc('User')->findByChatId($chat->id) as $participant)
        {
            $params =
            [
                'limit'         => $limit,
                'date_before'   => abs($chat->last_ts + 43200),     // + half a day offset
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
                            // fetch from all times and even muted, as the user has explicitly told us to do so
                            $synced += 1 * is_object($this->processMessageSync($user, $row,
                            [
                                'fetchMuted'    => true,
                                'limitToChatId' => $chat->id,
                                'maxTimeBack'   => -1,
                                'keepOld'       => true,
                                'notify'        => false,
                            ]));
                        }

                        if ($synced >= \Sys::cfg('sys.sync_depth'))
                        {
                            goto enough;
                        }

                        break;
                    }
                    else
                    {
                        $this->retry($params, ++$attempt);
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
     * @param bool $noMarks         - set 'true' to skip marking as unread through IM
     * @return int
     * @throws \Exception
     */
    public function syncAllByUserId($userId, $fetchMuted = false, $noMarks = false)
    {
        if (!$user = \Sys::svc('User')->findById($userId))
        {
            throw new \Exception('User does not exist');
        }

        $count = 0;

        // init email layer
        $imap = Inbox::init($user);

        $messageExtIds = $imap->getMessages(['ts_after' => time() - \Sys::cfg('sys.sync_period')]);

        $this->say('Prepared ' . count($messageExtIds) . ' messages to analyze.');

        foreach ($messageExtIds as $messageExtId)
        {
            $this->say("User {$user->email}, extId = $messageExtId");

            $emailData = $imap->getMessage($messageExtId);

            $count += 1 * !empty($this->processMessageSync($user, $emailData,
            [
                'fetchMuted'    => $fetchMuted,
                'fetchAll'      => true,
                'noMarks'       => $noMarks,
            ]));

            // sleep a bit to prevent API request queue growth
            usleep(200000);
        }

        return $count;
    }

    /**
     * Syncs a single message
     *
     * @param $userId
     * @param $messageExtId
     * @param bool $tryVerbose
     * @return bool|null|\StdClass
     * @throws \Exception
     */
    public function sync($userId, $messageExtId, $tryVerbose = true)
    {
        if (!$user = \Sys::svc('User')->findById($userId))
        {
            throw new \Exception('User does not exist.');
        }

        $imap = Inbox::init($user);
        $data = $imap->getMessage($messageExtId);

        if ($tryVerbose)
        {
            $this->say($data, 1);
        }

        // TODO: send a signal to notifier

        return $this->processMessageSync($user, $data, ['fetchMuted' => false]);
    }

    /**
     * Gets message data from external provider
     *
     * @param $userExtId
     * @param $extId
     * @param bool $retry
     * @return null
     */
    public function getDataByRefIdAndExtId($userExtId, $extId, $retry = true)
    {
        // TODO: t.b.d.

        return null;
    }

    /**
     * Removes excessive messages by a reference User ID
     *
     * @param $userId
     * @return int
     */
    public function removeOldByRefId($userId)
    {
        $stmt = \DB::prepare('DELETE FROM message WHERE ref_id=? AND ts<?', [$userId, time() - \Sys::cfg('sys.sync_period')]);
        $stmt->execute();
        $stmt->close();

        return \DB::l()->affected_rows;
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
     * @param array $options
     * @return bool|null|\StdClass
     * @throws \Exception
     */
    protected function processMessageSync($user, $messageData, $options = [])
    {
        $temporaryMessageExisted = false;

        // message must not exist
        $extId = $messageData['message_id'];

        $fetchMuted = isset ($options['fetchMuted']) ? $options['fetchMuted'] : false;
        $fetchAll = isset ($options['fetchAll']) ? $options['fetchAll'] : false;
        $limitToChatId = isset ($options['limitToChatId']) ? $options['limitToChatId'] : 0;
        $maxTimeBack = isset ($options['maxTimeBack']) ? $options['maxTimeBack'] : \Sys::cfg('sys.sync_period');
        $keepOld = isset ($options['keepOld']) ? $options['keepOld'] : false;
        $notify = isset ($options['notify']) ? $options['notify'] : true;
        $noMarks = isset ($options['noMarks']) ? $options['noMarks'] : false;

        if (!$message = $this->findByRefIdExtId($user->id, $extId))
        {
            if (!isset ($messageData['addresses']['from']))
            {
                $this->say('Error: no "from" address');
                return false;
            }

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

            // sometimes it may happen that user is not in the chat (e.g. stolen message), check this
            if (!in_array($user->email, $emails))
            {
                $this->say('Error: user is not in the chat');
                return false;
            }

            // try to clean emails
            foreach ($emails as $k => $email)
            {
                $emails[$k] = trim($email, "'");
            }

            try
            {
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
                        $this->say('Error: only one person in chat');
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
            }
            catch (\Exception $e)
            {
                $this->say('Error: cannot init chat or chat ID limitation is set');
                return false;
            }


            if (!$chat)
            {
                throw new \Exception('Chat does not exist');
            }

            // thus, allow a chat to be created first
            if ($maxTimeBack > 0 && $messageData['date'] < time() - $maxTimeBack)
            {
                $this->say('Notice: message is too old');
                return false;
            }

            if (!$fetchMuted /*&& count($emails) == 2*/)
            {
                // check that you want any messages in this chat
                // message sync on behalf of a bot will not happen

                $recipient = \Sys::svc('User')->findByEmail(@$messageData['addresses']['to'][0]['email']);
                $flags = \Sys::svc('Chat')->getFlags($chat->id, @$recipient->id);

                if ($flags && $flags->muted)
                {
                    $this->say('Notice: message is muted');
                    return false;
                }
            }

            // assure the message is not a draft
            if (isset ($messageData['folders'])) foreach ($messageData['folders'] as $folder)
            {
                if (stripos($folder, 'draft') !== false)
                {
                    $this->say('Notice: message is a draft');
                    return false;
                }
            }

            // === process the message content ===

            $files = [];

            if (isset ($messageData['files']))
            {
                foreach ($messageData['files'] as $file)
                {
                    $files[] = array
                    (
                        'name'  => $file['name'],
                        'type'  => $file['type'],
                        'size'  => $file['size'],
                    );
                }
            }

            if ((!isset ($messageData['body'][0]) || !strlen($messageData['body'][0]['content'])) && empty ($files))
            {
                $this->say('Error: no payload');
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
                $this->say('Notice: duplicate message');
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
                $this->say('Notice: message has no real content');
                return false;
            }

            // check if this email refers to a temporary message and kill the latter with file
            if ($tempMessageId = @$messageData['headers']['X-Temporary-ID'][0])
            {
                if ($tempMessage = $this->findById($tempMessageId))
                {
                    $this->delete($tempMessage);
                    $temporaryMessageExisted = true;
                }
            }

            $subject = mb_convert_encoding($this->clearSubject($messageData['subject']), 'UTF-8');
            $body = mb_convert_encoding($content, 'UTF-8');

            // check other bodies if they contain anything useful
            foreach ($messageData['body'] as $item)
            {
                if ($item['type'] == 'text/calendar')
                {
                    // calendar found => remove all the attachments, adjust body and subject
                    $files = null;
                    $calendar = Calendar::parse($item['content']);
                    $body = json_encode(array
                    (
                        'type'      => 'ics',
                        'widget'    => $calendar,
                    ));
                    $subject = '';
                }
            }

            $message = $this->create(array
            (
                'ext_id'    => $extId,
                'user_id'   => $senderId,
                'chat_id'   => $chat->id,
                'ref_id'    => $user->id,
                'subject'   => $subject,
                'body'      => $body,
                'files'     => empty ($files) ? '' : json_encode($files),
                'ts'        => $messageData['date'],
             ));

            // message received, update chat if it's a new one
            $chat->last_ts = max($messageData['date'], $chat->last_ts);
            \Sys::svc('Chat')->update($chat);

            if ($senderId != $user->id && !$fetchAll)
            {
                // there were one or more new foreign messages and this is not a FetchAll mode -> reset 'read' flag
                \Sys::svc('Chat')->setReadFlag($chat->id, $user->id, 0);
            }

            if (!$temporaryMessageExisted && $notify)
            {
                \Sys::svc('Notify')->send(
                [
                    'cmd'       => 'notify',
                    'userIds'   => [$user->id],
                    'chatId'    => $chat->id,
                    'noMarks'   => $noMarks,
                ]);
            }

            // remove old messages if not explicitly instructed
            if (!$keepOld)
            {
                $this->removeOldByRefId($user->id);
            }
        }

        return $message;
    }

    /**
     * Assures there are no duplicates by user-chat-ts among the real messages
     *
     * @param $userId
     * @param $chatId
     * @param $ts
     * @return bool
     */
    protected function isUnique($userId, $chatId, $ts)
    {
        return !\DB::row('SELECT id FROM message WHERE user_id=? AND chat_id=? AND ts=? AND ext_id IS NOT NULL LIMIT 1', [$userId, $chatId, $ts]);
    }

    /**
     * @param $subject
     * @return string
     */
    public function clearSubject($subject)
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

        // rare bullshit
        $content = preg_replace("/^--_=_.*$/ims", '', $content);

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

        return mb_ereg_replace('^[[:space:]]*([\s\S]*?)[[:space:]]*$', '\1', $content);
    }

    /**
     * A service function to report and wait
     *
     * @param $params
     * @param $attempt
     */
    protected function retry($params, $attempt)
    {
        $timeout = (int)(5 + $attempt/2);

        $this->say("Sync error, attempt #$attempt in $timeout seconds");
        sleep($timeout);
        $this->say($params, 1);
    }

    protected function say($what, $print = 0)
    {
        if (@$GLOBALS['-SYS-VERBOSE'])
        {
            if ($print) print_r($what);
            else echo "$what\n";
        }
    }
}
