<?php

namespace App\Service;

use App\Model\FileParser\Calendar;
use App\Model\Html2Text;
use App\Model\Inbox\Inbox;
use MongoDB\BSON\ObjectID;

class Message
{
    /**
     * Returns the last message from the chat
     *
     * @param $chat
     * @return null|\StdClass
     */
    public static function getLastByChat($chat)
    {
        if (isset ($chat->messages))
        {
            usort($chat->messages, function ($a, $b)
            {
                return $b->ts <=> $a->ts;
            });

            return $chat->messages[0];
        }

        return null;
    }

    /**
     * Finds a Message by its external ID
     *
     * @param $refUserId
     * @param $extId
     * @return null|\StdClass
     */
    public static function findByRefIdAndExtId($refUserId, $extId)
    {
        return Chat::findOne(['messages.refId' => $refUserId, 'messages.extId' => $extId]);
    }

    /**
     * Returns the total count of messages synced for this Chat
     *
     * @param $chat
     * @return mixed
     */
    public static function countByChatId($chat)
    {
        return count($chat->messages);
    }

    /**
     * Find last message in chat that is not
     *
     * @param $chat
     * @return null|\StdClass
     */
    public static function findByLastRealByChat($chat)
    {
        // latest messages are at the top
        foreach ($chat->messages ?? [] as $message)
        {
            if (isset ($message->extId))
            {
                return $message;
            }
        }

        return null;
    }

    /**
     * Syncs all messages for the User
     *
     * @param $userId
     * @return int
     * @throws \Exception
     */
    public static function syncAllByUserId($userId)
    {
        if (!is_object($userId))
        {
            if (!$user = User::findOne(['_id' => new ObjectID($userId)]))
            {
                throw new \Exception('User does not exist');
            }
        }
        else $user = $userId;

        $count = 0;

        // init email layer
        $imap = Inbox::init($user);

        $messageExtIds = $imap->getMessages(['ts_after' => time() - \Sys::cfg('sys.sync_period')]);

        if (count($messageExtIds) && $messageExtIds[0] != @$user->lastMuid)
        {
            $user->lastMuid = $messageExtIds[0];
            User::update($user);
        }

        self::say('Prepared ' . count($messageExtIds) . ' messages to analyze.');

        foreach ($messageExtIds as $messageExtId)
        {
            try
            {
                self::say("User {$user->email}, extId = $messageExtId");

                $emailData = $imap->getMessage($messageExtId);

                // this check is here just in case
                $maxTimeBack = \Sys::cfg('sys.sync_period');
                if ($maxTimeBack > 0 && $emailData['date'] < time() - $maxTimeBack - 172800)
                {
                    // most probably all the next email will be 'too old'
                    self::say('Process stopped as definitely-too-old message was reached');
                    break;
                }

                $count += 1 * !empty (self::processMessageSync($user, $emailData,
                [
                    'fetchAll'  => true,
                    'usePush'   => false,           // don't spam during a mass sync
                ]));
            }
            catch (\Exception $e)
            {
                self::say('ERROR ' . $e->getMessage());
                usleep(500000);
            }

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
     * @param array $options
     * @return bool|object
     * @throws \Exception
     */
    public static function sync($userId, $messageExtId, $tryVerbose = true, $options = [])
    {
        if (!is_object($userId))
        {
            if (!$user = User::findOne(['_id' => new ObjectID($userId)]))
            {
                throw new \Exception('User does not exist');
            }
        }
        else $user = $userId;

        $imap = Inbox::init($user);
        $data = $imap->getMessage($messageExtId);

        if ($tryVerbose)
        {
            self::say($data, 1);
        }

        $message = self::processMessageSync($user, $data, $options, $imap);

        return $message;
    }

    /**
     * Gets message data from mailbox
     *
     * @param $userId
     * @param $messageExtId
     * @return mixed
     */
    public static function getDataByRefIdAndExtId($userId, $messageExtId)
    {
        return Inbox::init($userId)->getMessage($messageExtId);
    }

    /**
     * Removes excessive messages from a message array
     *
     * @param array $messages
     * @return array $messages
     */
    public static function removeOld($messages)
    {
        $messages = $messages ?? [];

        if (count($messages) > 100)
        {
            $messages = array_slice($messages, 0, 100);
        }

        foreach ($messages as $k => $v)
        {
            if ($v->ts < time() - \Sys::cfg('sys.sync_period'))
            {
                unset ($messages[$k]);
            }
        }

        return array_values($messages);
    }

    // ============================
    // ===  internal functions  ===
    // ============================

    /**
     * @param $user                 -- recipient user
     * @param $messageData
     * @param array $options
     * @param object $imapObject
     * @return bool | object
     * @throws \Exception
     */
    protected static function processMessageSync($user, $messageData, $options = [], $imapObject = null)
    {
        $temporaryMessageExisted = false;

        // message must not exist
        $extId = $messageData['message_id'];

        $fetchAll = isset ($options['fetchAll']) ? $options['fetchAll'] : false;
        $limitToChatId = isset ($options['limitToChatId']) ? $options['limitToChatId'] : 0;
        $maxTimeBack = isset ($options['maxTimeBack']) ? $options['maxTimeBack'] : \Sys::cfg('sys.sync_period');
        $keepOld = isset ($options['keepOld']) ? $options['keepOld'] : false;
        $notify = isset ($options['notify']) ? $options['notify'] : true;
        $usePush = isset ($options['usePush']) ? $options['usePush'] : true;

        // assure the message is not spammy shit
        if (isset ($messageData['folders'])) foreach ($messageData['folders'] as $folder)
        {
            $folder = strtolower($folder);

            if (in_array($folder, ['draft', 'chat', 'trash', 'spam']))
            {
                self::say("Notice: message has illegal label '$folder'");
                return false;
            }
        }

        // first and foremost find associated chat

        if (!isset ($messageData['addresses']['from']))
        {
            self::say('Error: no "from" address');
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

        if (isset ($messageData['addresses']['bcc'])) foreach ($messageData['addresses']['bcc'] as $bcc)
        {
            $emails[] = $bcc['email'];
            $names[$bcc['email']] = @$bcc['name'];
        }

        // we don't need duplicates
        $emails = array_unique($emails);

        // sometimes it may happen that user is not in the chat (e.g. stolen message), check this
        if (!in_array($user->email, $emails))
        {
            self::say('Error: user is not in the chat');
            return false;
        }

        // try to clean emails
        foreach ($emails as $k => $email)
        {
            $emails[$k] = strtolower(trim($email, "'"));
        }

        // chat may not exist -> init and mute if necessary
        if (!$chat = Chat::findByEmails($emails))
        {
            if ($limitToChatId)
            {
                // chat does not exist while ID restriction is imposed -> leave
                return false;
            }

            if (count($emails) < 2)
            {
                // the cannot be less than 2 people in chat
                self::say('Error: only one person in chat');
                return false;
            }
            try
            {
                $chat = Chat::init($emails, $names, true);
            }
            catch (\Exception $e)
            {
                return false;
            }
        }
        else
        {
            if ($limitToChatId && $chat->_id != $limitToChatId)
            {
                // chat exists, but does not fulfill the ID requirement, used by moreByChatId
                return false;
            }
        }

        if (!$chat)
        {
            throw new \Exception('Chat does not exist');
        }

        foreach ($chat->messages ?? [] as $message)
        {
            // message with this extID for this User is already present
            if (@$message->refId == $user->_id && $message->extId == $extId)
            {
                return $message;
            }
        }

        if ($maxTimeBack > 0 && $messageData['date'] < time() - $maxTimeBack)
        {
            self::say('Notice: message is too old');
            return false;
        }

        $recipient = User::findOne(['email' => @$messageData['addresses']['to'][0]['email']]);
        $flags = Chat::getFlags($chat, $recipient ? $recipient->_id : 0);


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
            self::say('Error: no payload');
            return false;
        }

        if ($messageData['addresses']['from']['email'] == $user->email)
        {
            // the head user is the author
            $senderId = $user->_id;
        }
        else
        {
            $sender = User::findOne(['email' => $messageData['addresses']['from']['email']]);
            $senderId = $sender->_id;
        }


        // avoid duplicating by user_id-chat-ts key
        if (!self::isUnique($senderId, $chat, $messageData['date']))
        {
            self::say('Notice: duplicate message');
            return false;
        }

        $content = self::clearContent(@$messageData['body'][0]['type'], @$messageData['body'][0]['content']);

        // check for forwarded messages
        if (stripos($messageData['subject'], 'FWD:', 0) === 0)
        {
            $content .= "[sys:fwd]";
        }

        if (!mb_strlen($content) && empty ($files))
        {
            // avoid empty replies
            self::say('Notice: message has no real content');
            return false;
        }

        // check if this email refers to a temporary message and kill the latter with file
        if ($tempMessageId = @$messageData['headers']['x-temporary-id'][0])
        {
            foreach ($chat->messages ?? [] as $k => $message)
            {
                if (@$message->id == $tempMessageId)
                {
                    unset ($chat->messages[$k]);
                    $temporaryMessageExisted = true;
                    break;
                }
            }
        }

        $subject = mb_convert_encoding(self::clearSubject($messageData['subject']), 'UTF-8');
        $body = mb_convert_encoding($content, 'UTF-8');

        // check other bodies if they contain anything useful
        foreach ($messageData['body'] as $item)
        {
            if ($item['type'] == 'text/calendar')
            {
                // calendar found => remove all the attachments, adjust body and subject
                $files = null;
                $calendar = Calendar::parse($item['content']);
                $body =
                [
                    'type'      => 'ics',
                    'widget'    => $calendar,
                ];
                $subject = '';
            }
        }

        $chat->messages = $chat->messages ?? [];

        if ($flags && $flags->muted)
        {
            // OK, the chat is muted
            // allow fetching, but keep 1 message in the chat only => clear the whole chat before sync
            $chat->messages = [];
            $usePush = false;
        }
        else if (!$keepOld)
        {
            // remove old messages if not explicitly instructed
            $chat->messages = self::removeOld($chat->messages);
        }

        $messageStructure =
        [
            'id'        => (new ObjectID())->__toString(),
            'extId'     => $extId,
            'userId'    => $senderId,
            'refId'     => $user->_id,
            'subj'      => $subject,
            'body'      => $body,
            'files'     => $files,
            'ts'        => $messageData['date'],
        ];

        array_unshift($chat->messages, $messageStructure);

        if ($senderId != $user->_id && !$fetchAll)
        {
            // there were one or more new foreign messages and this is not a FetchAll mode
            //  -> mark this chat as unread for everyone except User
            foreach ($chat->users as $k => $userRow)
            {
                $chat->users[$k]->read = 0;
            }
        }

        $chat->lastTs = max($messageData['date'], $chat->lastTs ?? 0);

        // run chat update
        Chat::update($chat, ['messages' => $chat->messages, 'lastTs' => $chat->lastTs, 'users' => $chat->users]);

        // notify everyone except sender
        $notifyThese = [];
        foreach ($chat->users as $user) if ($user->id != $senderId)
        {
            $notifyThese[] = $user->id;
        }

        if (!$temporaryMessageExisted && $notify)
        {
            if (!empty ($files))
            {
                if (strlen($body)) $body .= "\n";
                $body .= '📄 × ' . count($files);
            }
            Notify::auto($notifyThese, ['cmd' => 'chat:update', 'chatId' => $chat->_id], $usePush ? ['title' => $subject, 'body' => $body] : null);
        }

        // generate previews
        $fileCount = 0;
        if (isset ($messageData['files'])) foreach ($messageData['files'] as $file)
        {
            File::createAttachmentPreview($imapObject, $messageData, $chat->_id, $messageStructure['id'], $fileCount, $file['type']);

            ++$fileCount;
        }

        return (object) $messageStructure;
    }

    /**
     * Assures there are no duplicates by user-chat-ts among the real messages
     *
     * @param $userId
     * @param $chat
     * @param $ts
     * @return bool
     */
    protected static function isUnique($userId, $chat, $ts)
    {
        foreach ($chat->messages ?? [] as $message)
        {
            if ($message->userId == $userId && $message->ts == $ts)
            {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $subject
     * @return string
     */
    public static function clearSubject($subject)
    {
        $items = ['RE','FWD','FW','VS','VB','SV'];

        // remove things in brackets
        $subject = trim(preg_replace('/\[[^]]+\]/', '', $subject));

        foreach ($items as $item)
        {
            $item .= ':';
            if (stripos($subject, $item, 0) === 0)
            {
                $subject = trim(str_ireplace($item, '', $subject));
                // break;
            }
        }

        return trim($subject);
    }

    /**
     * @param $type
     * @param $content
     * @return null
     */
    public static function clearContent($type, $content)
    {
        if ($type != 'keep')
        {
            $content = str_ireplace(['<br />','<br>','<br/>'], "\n", $content);

            if ($type == 'text/html')
            {
                /*    // do not let HTML spam in
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
                    $content = strip_tags($content);
                    $content = html_entity_decode($content);*/

                $content = preg_replace('/<style(.*)<\/style>/im', '', $content);
                $content = preg_replace('/<blockquote(.*)<\/blockquote>/im', '', $content);

                try
                {
                    $content = Html2Text::convert($content);
                }
                catch (\Exception $e)
                {
                    return null;
                }
            }

            $content = str_replace("\r\n", "\n", $content);
            $content = preg_replace('/\n{3,}/', "\n\n", $content);
        }

        $quoteHeadersRegex = array
        (
            // English
            '/^([^\n]*On).*(wrote:).*$/sm',

            // Swedish
            '/^([^\n]*Den).*(skrev:).*$/sm',
            '/^[^\n]*(<.+@.+>).*(skrev:).*$/sm',
            '/^[^\n]*(skrev).*(<.+@.+>:).*$/sm',

            // Lithuanian
            '/^[^\n]*\d{4} m\..*(<.+@.+> rašė:).*$/sm',

            // Russian
            '/^[^\n]*\p{L}+, \d{1,2} \p{L}+ [\d+: ,]+ от .+ (<.+@.+>:).*$/sm',

            // generic
            '/^[^\n]*[\d.]{10}, [\d:]{5}, \".+\" (<.+@.+>:).*$/sm',
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

        $content = mb_ereg_replace('^[[:space:]]*([\s\S]*?)[[:space:]]*$', '\1', $content);

        // stylistic cleaning
        // try to remove signature
        $content = preg_replace('/\s((best|kind) regards,).*/si', '', $content);
        if (strpos($content, "\n--\n") !== false)
        {
            $content = preg_replace('/(?!.*[\r\n]--).*/s', '', $content);
        }

        return $content;
    }

    /**
     * A service function to report and wait
     *
     * @param $params
     * @param $attempt
     */
    protected static function retry($params, $attempt)
    {
        $timeout = (int)(5 + $attempt/2);

        self::say("Sync error, attempt #$attempt in $timeout seconds");
        sleep($timeout);
        self::say($params, 1);
    }

    /**
     * @param $what
     * @param int $print
     */
    protected static function say($what, $print = 0)
    {
        if (@$GLOBALS['-SYS-VERBOSE'])
        {
            if ($print) print_r($what);
            else echo "$what\n";
        }
        else
        {
            if (!isset ($GLOBALS['-SYS-LOG'])) $GLOBALS['-SYS-LOG'] = '';

            if ($print) $GLOBALS['-SYS-LOG'] .= json_encode($what) . "\n";
            else $GLOBALS['-SYS-LOG'] .= "$what\n";
        }
    }
}
