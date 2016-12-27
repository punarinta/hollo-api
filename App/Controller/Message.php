<?php

namespace App\Controller;

use App\Model\Inbox\Inbox;
use MongoDB\BSON\ObjectID;
use \App\Service\Message as MessageSvc;
use \App\Service\Smtp as SmtpSvc;
use \App\Service\Chat as ChatSvc;
use \App\Service\Chat as UserSvc;

/**
 * Class Message
 * @package App\Controller
 * @doc-api-path /api/message
 */
class Message extends Generic
{
    /**
     * Show the original message
     *
     * @doc-var     (string) id!       - Message ID
     * @doc-var     (int) bodyId       - Body ID
     *
     * @return mixed
     * @throws \Exception
     */
    static public function showOriginal()
    {
        if (!$id = \Input::data('id'))
        {
            throw new \Exception('Message ID not provided.');
        }

        // TODO: passing down chat ID will save resources
        if (!$chat = ChatSvc::findOne(['messages.id' => $id]))
        {
            throw new \Exception('Message does not exist');
        }

        if (!ChatSvc::hasAccess($chat, \Auth::user()->_id))
        {
            throw new \Exception('Access denied.', 403);
        }

        foreach ($chat->messages ?? [] as $message)
        {
            if ($message->id == $id)
            {
                if (!$data = MessageSvc::getDataByRefIdAndExtId($message->refId, $message->extId))
                {
                    return false;
                }

                return $data['body'][\Input::data('bodyId') ?? 0];
            }
        }

        return false;
    }

    /**
     * Reply to a message or compose a new one
     *
     * @doc-var     (string) body!          - Message body.
     * @doc-var     (string) chatId!        - Chat ID, used for temporary message referencing and notifications.
     * @doc-var     (string) messageId      - Hollo's message ID to reply to.
     * @doc-var     (string) subject        - Message subject.
     * @doc-var     (array) files           - Message attachments.
     * @doc-var     (string) file[].name    - File name.
     * @doc-var     (string) file[].type    - File MIME type.
     * @doc-var     (int) file[].size       - File size.
     * @doc-var     (string) file[].data    - Base64 file data.
     *
     * @return bool
     * @throws \Exception
     */
    static public function send()
    {
        $body = trim(\Input::data('body'));
        $files = \Input::data('files');

        if (!$body && !$files)
        {
            // TODO: sanitize?
            throw new \Exception('Neither body provided, nor files.');
        }

        if (!$chatId = \Input::data('chatId'))
        {
            throw new \Exception('Chat ID is not specified.');
        }

        $chat = ChatSvc::findOne(['_id' => new ObjectID($chatId)]);

        if (!ChatSvc::hasAccess($chat, \Auth::user()->_id))
        {
            throw new \Exception('Access denied.', 403);
        }

        // create a temporary message in the DB
        // message will be kept until the real one arrives

        $dbFiles = [];

        // sorry, we cannot store files in the DB
        foreach ($files as $file)
        {
            $dbFiles[] = array
            (
                'name'  => $file['name'],
                'type'  => $file['type'],
                'size'  => $file['size'],
            );
        }

        $messageStructure =
        [
            'id'        => (new ObjectID())->__toString(),
            'userId'    => \Auth::user()->_id,
            'subj'      => \Input::data('subject'),
            'body'      => $body,
            'files'     => $dbFiles,
            'ts'        => time(),
        ];

        $chat->messages = $chat->messages ?? [];
        array_unshift($chat->messages, $messageStructure);

        // mark chat as unread for everyone except you
        foreach ($chat->users as $k => $userRow)
        {
            if ($userRow->id != \Auth::user()->_id)
            {
                $chat->users[$k]->read = 0;
            }
        }

        // mark chat as just updated
        $chat->lastTs = time();
        ChatSvc::update($chat, ['messages' => $chat->messages, 'lastTs' => $chat->lastTs, 'users' => $chat->users]);

        // check if you are chatting with a bot
    //    if ($bots = \DB::rows("SELECT u.* FROM user AS u LEFT JOIN chat_user AS cu ON cu.user_id = u.id WHERE cu.chat_id=? AND u.email LIKE '%@bot.hollo.email' ", [$chatId]))
    //    {
    //        foreach ($bots as $bot)
    //        {
    //            \Sys::svc('Bot')->talk($bot, $chatId, $body);
    //        }
    //    }
    //    else
    //    {
            SmtpSvc::setupThread(\Auth::user(), $chat, $messageStructure['id']);
            $res = SmtpSvc::send($chat, $body, \Input::data('subject'), $files);
    //    }

        return true; // $res;
    }

    /**
     * Forwards a message into a chat
     *
     * @doc-var     (string) id!            - Message ID.
     * @doc-var     (string) fromChatId!    - Donor chat ID.
     * @doc-var     (string) toChatId!      - Recipient chat ID.
     */
    static public function forward()
    {
        if (!$id = \Input::data('id'))
        {
            throw new \Exception('Message ID is not specified.');
        }

        if ((!$fromChatId = \Input::data('fromChatId')) || (!$toChatId = \Input::data('toChatId')))
        {
            throw new \Exception('Chat ID is not specified.');
        }

        $fromChat = ChatSvc::findOne(['_id' => new ObjectID($fromChatId)]);
        $toChat = ChatSvc::findOne(['_id' => new ObjectID($toChatId)]);

        if (!ChatSvc::hasAccess($fromChat, \Auth::user()->_id) || !ChatSvc::hasAccess($toChat, \Auth::user()->_id))
        {
            throw new \Exception('Access denied.', 403);
        }

        if (isset ($fromChat->messages))
        {
            foreach ($fromChat->messages as $message)
            {
                if ($message->id == $id)
                {
                    // we have to form the new body here
                    $refUser = UserSvc::findOne(['_id' => new ObjectID($message->refId)]);
                    $inbox = Inbox::init($refUser);

                    $newBody = '';
                    $newSubject = 'FWD: ' . $message->subj;

                    if ($data = $inbox->getMessage($message->extId))
                    {
                        $content = mb_convert_encoding($data['body'][0]['content'], 'UTF-8');

                        $body = [];
                        foreach (preg_split("/\r\n|\n|\r/", $content) as $line)
                        {
                            $body[] = '> ' . $line;
                        }

                        $ts = date('r', $data['date']);
                        $name = explode('@', $data['addresses']['from']['email']);
                        $newBody = "-------- Beginning of forwarded message--------\n";
                        $newBody .= "On {$ts}, {$name[0]} <{$data['addresses']['from']['email']}> wrote:\n\n" . implode("\n", $body);
                    }

                    // create a temporary message
                    $messageStructure =
                    [
                        'id'        => (new ObjectID())->__toString(),
                        'userId'    => \Auth::user()->_id,
                        'subj'      => $newSubject,
                        'body'      => $newBody,
                        'files'     => $message->files,
                        'ts'        => time(),
                    ];

                    $toChat->messages = $toChat->messages ?? [];
                    array_unshift($toChat->messages, $messageStructure);

                    // mark chat as unread for everyone except you
                    foreach ($toChat->users as $k => $userRow)
                    {
                        if ($userRow->id != \Auth::user()->_id)
                        {
                            $toChat->users[$k]->read = 0;
                        }
                    }

                    // get files from donor message
                    $files = [];
                    $offset = 0;
                    if (@$message->files)
                    {
                        // give more time for fetching files
                        ini_set('max_execution_time', 30);

                        foreach ($message->files as $file)
                        {
                            $files[] = array
                            (
                                'name'  => $file['name'],
                                'type'  => $file['type'],
                                'size'  => $file['size'],
                                'b64'   => base64_encode($inbox->getFileData($message->extId, $offset++)),  // return and increment
                            );
                        }
                    }

                    // prepare a generic sender
                    SmtpSvc::setupThread(\Auth::user(), null, $messageStructure['id']);

                    // send prepared message
                    $res = SmtpSvc::send($toChat, $newBody, $newSubject, $files);

                    // mark the chat as just updated
                    $toChat->lastTs = time();
                    ChatSvc::update($toChat, ['messages' => $toChat->messages, 'lastTs' => $toChat->lastTs, 'users' => $toChat->users]);

                    return true; // $res;
                }
            }
        }

        return false;
    }
}
