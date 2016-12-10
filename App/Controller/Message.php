<?php

namespace App\Controller;

use MongoDB\BSON\ObjectID;
use \App\Service\User as UserSvc;
use \App\Service\Message as MessageSvc;
use \App\Service\Smtp as SmtpSvc;
use \App\Service\Chat as ChatSvc;

/**
 * Class Message
 * @package App\Controller
 * @doc-api-path /api/message
 */
class Message extends Generic
{
    /**
     * Returns messages within a Chat
     *
     * @doc-var     (string) chatId     - Chat ID.
     * @doc-var     (string) subject    - Filter by subject.
     * @doc-var     (bool) ninja        - Ninja mode, set 'true' to keep messages unread.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function findByChatId()
    {
        if (!$chatId = \Input::data('chatId'))
        {
            throw new \Exception('Chat ID not provided.');
        }

        $muted = 0;
        $scanIds = [];

        if (!$chat = ChatSvc::findOne
        (
            ['_id' => new ObjectID($chatId)],
            ['projection' => ['messages.refId' => 0]]
        ))
        {
            throw new \Exception('Chat does not exist.');
        }

        if (!ChatSvc::hasAccess($chat, \Auth::user()->_id))
        {
            throw new \Exception('Access denied.', 403);
        }

        $chatUsers = $chat->users;
        $chat->messages = $chat->messages ?? [];

        foreach ($chatUsers as $k => $userItem)
        {
            if ($userItem->id == \Auth::user()->_id)
            {
                $muted = $userItem->muted;
            //    $chatUsers[$k]->read = 1;
            }
            else
            {
                $scanIds[] = new ObjectID($userItem->id);
            }
        }

        $users = [];
        foreach (UserSvc::findAll(['_id' => ['$in' => $scanIds]], ['projection' => ['_id' => 1, 'name' => 1, 'email' => 1]]) as $user)
        {
            $users[$user->_id] = array
            (
                'id'    => $user->_id,
                'name'  => @$user->name,
                'email' => $user->email,
            );
        }

        foreach ($chat->messages as $k => $message)
        {
            if (isset ($users[$message->userId]))
            {
                $chat->messages[$k]->from = $users[$message->userId];
            }
            else
            {
                // TODO: use one more cache, do not mix with $users
                $chat->messages[$k]->from = UserSvc::findOne(['_id' => new ObjectID($message->userId)], ['projection' => ['_id' => 1, 'name' => 1, 'email' => 1]]);
                $chat->messages[$k]->from->id = $chat->messages[$k]->from->_id;
                unset ($chat->messages[$k]->from->_id);
            }
            unset ($chat->messages[$k]->userId);
        }

        ChatSvc::setReadFlag($chat, \Auth::user()->_id, 1);

        foreach ($chat->messages as $k => $v)
        {
            $chat->messages[$k]->subject = $chat->messages[$k]->subj;
            unset ($chat->messages[$k]->subj);
        }

        usort($chat->messages, function ($a, $b)
        {
            return $b->ts <=> $a->ts;
        });

        return array
        (
            'chat'   => array
            (
                'id'    => $chat->_id,
                'name'  => @$chat->name,
                'muted' => $muted,
                'users' => array_values($users),
            ),
            'messages'  => $chat->messages,
        );
    }

    /**
     * Returns last messages in the Chat
     *
     * @doc-var     (string) chatId!            - Chat ID
     * @doc-var     (string) lastMessageId!     - Message ID after which one has to count
     *
     * @return array
     * @throws \Exception
     */
    static function getAllAfter()
    {
        if (!$chatId = \Input::data('chatId'))
        {
            throw new \Exception('Chat ID not provided.');
        }

        if (!$chat = ChatSvc::findOne(['_id' => new ObjectID($chatId)]))
        {
            throw new \Exception('Chat not found.');
        }

        if (!ChatSvc::hasAccess($chat, \Auth::user()->_id))
        {
            throw new \Exception('Access denied', 403);
        }

        $items = [];
        $messages = $chat->messages ?? [];
        $lastMessageId = \Input::data('lastMessageId');

        usort($messages, function ($a, $b)
        {
            // latest messages will thus go first
            return $b->ts <=> $a->ts;
        });

        foreach ($messages as $message)
        {
            if ($message->id == $lastMessageId)
            {
                break;
            }

            $items[] = $message;
        }

        return $items;
    }

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

        $chat = ChatSvc::findOne(['_id' => new ObjectID($chatId)]);

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
            SmtpSvc::setupThread(\Auth::user()->_id, $chat, $messageStructure['id']);
            $res = SmtpSvc::send($chat, $body, \Input::data('subject'), $files);
    //    }

        return true; // $res;
    }

    /**
     * Generates a list of last messages from unread chats
     *
     * @doc-var     (bool) muted        - Filter with muted flag.
     *
     * @throws \Exception
     */
    static public function buildQuickStack()
    {
        $items = [];
        $filters = [['mode' => 'read', 'value' => 0]];

        if (\Input::data('muted') !== null)
        {
            $filters[] = array
            (
                'mode'  => 'muted',
                'value' => \Input::data('muted'),
            );
        }

        // NB: newest are on the top
        foreach (ChatSvc::findAllByUserId(\Auth::user()->_id, $filters) as $chat)
        {
            if ($message = MessageSvc::getLastByChat($chat))
            {
                $items[] = array
                (
                    'id'        => $message->id,
                    'body'      => $message->body,
                    'subject'   => $message->subj,
                    'files'     => $message->files,
                    'ts'        => $message->ts,
                    'fromId'    => $message->userId,
                    'refId'     => $message->refId,
                    'chatId'    => $chat->_id,               // the chat will already be in list, so just get all the data from there
                );
            }
        }

        return $items;
    }
}
