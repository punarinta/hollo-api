<?php

namespace App\Controller;
use MongoDB\BSON\ObjectID;

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
     * @doc-var     (int) chatId        - Chat ID.
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

        if (!$chat = \Sys::svc('Chat')->findOne
        (
            ['_id' => new ObjectID($chatId)],
            ['projection' => ['messages.refId' => 0]]
        ))
        {
            throw new \Exception('Chat does not exist.');
        }

        if (!\Sys::svc('Chat')->hasAccess($chat, \Auth::user()->_id))
        {
            throw new \Exception('Access denied.', 403);
        }

        $chatUsers = $chat->users;

        foreach ($chatUsers as $k => $userItem)
        {
            if ($userItem->id == \Auth::user()->_id)
            {
                $muted = $userItem->muted;
                $chatUsers[$k]->read = 1;
            }
            else
            {
                $scanIds[] = new ObjectID($userItem->id);
            }
        }

        $users = [];
        foreach (\Sys::svc('User')->findAll(['_id' => ['$in' => $scanIds]], ['projection' => ['_id' => 1, 'name' => 1, 'email' => 1]]) as $user)
        {
            $users[$user->_id] = array
            (
                'id'    => $user->_id,
                'name'  => $user->name,
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
                $chat->messages[$k]->from = \Sys::svc('User')->findOne(['_id' => new ObjectID($message->userId)]);
            }
            unset ($chat->messages[$k]->userId);
        }

        \Sys::svc('Chat')->update($chat, ['users' => $chatUsers]);

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
     * Returns N more messages for a Chat
     *
     * @doc-var     (int) chatId!        - Chat ID
     *
     * @return mixed
     * @throws \Exception
     */
    static public function moreByChatId()
    {
        if (!$chatId = \Input::data('chatId'))
        {
            throw new \Exception('Email not provided.');
        }

        if (!\Sys::svc('Chat')->hasAccess($chatId, \Auth::user()->id))
        {
            throw new \Exception('Access denied.', 403);
        }

        if (!$chat = \Sys::svc('Chat')->findById($chatId))
        {
            throw new \Exception('Chat does not exist.');
        }

        return \Sys::svc('Message')->moreByChat($chat, \Auth::user());
    }

    /**
     * Returns last message in the Chat
     *
     * @doc-var     (int) chatId!       - Chat ID
     *
     * @return mixed
     * @throws \Exception
     */
    static function getLastChatMessage()
    {
        if (!$chatId = \Input::data('chatId'))
        {
            throw new \Exception('Email not provided.');
        }

        $message = \Sys::svc('Message')->getLastByChatId($chatId);
        $user = \Sys::svc('User')->findById($message->user_id);

        return array
        (
            'id'        => $message->id,
            'ts'        => $message->ts,
            'body'      => $message->body,
            'subject'   => \Sys::svc('Message')->clearSubject($message->subject),
            'from'      => array
            (
                'id'    => $user->id,
                'email' => $user->email,
                'name'  => $user->name,
            ),
            'files'     => json_decode($message->files, true),
        );
    }

    /**
     * Show the original message
     *
     * @doc-var     (int) id!       - Message ID
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

        if (!$message = \Sys::svc('Message')->findById($id))
        {
            throw new \Exception('Message does not exist');
        }

        if (!$data = \Sys::svc('Message')->getDataByRefIdAndExtId($message->ref_id, $message->ext_id))
        {
            return false;
        }

        return $data['body'][0];
    }

    /**
     * Reply to a message or compose a new one
     *
     * @doc-var     (string) body!          - Message body.
     * @doc-var     (int) chatId!           - Chat ID, used for temporary message referencing and notifications.
     * @doc-var     (int) messageId         - Hollo's message ID to reply to.
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

        $message = \Sys::svc('Message')->create(array
        (
            'ext_id'    => '',
            'user_id'   => \Auth::user()->id,
            'chat_id'   => $chatId,
            'subject'   => \Input::data('subject'),
            'body'      => $body,
            'files'     => empty ($dbFiles) ? '' : json_encode($dbFiles),
            'ts'        => time(),
        ));

        // mark chat as read for everyone except you
        \Sys::svc('Chat')->setReadFlag($chatId, -\Auth::user()->id, 0);

        // mark chat as just updated
        $chat = \Sys::svc('Chat')->findById($chatId);
        $chat->last_ts = time();
        \Sys::svc('Chat')->update($chat);

        // check if you are chatting with a bot
        if ($bots = \DB::rows("SELECT u.* FROM user AS u LEFT JOIN chat_user AS cu ON cu.user_id = u.id WHERE cu.chat_id=? AND u.email LIKE '%@bot.hollo.email' ", [$chatId]))
        {
            foreach ($bots as $bot)
            {
                \Sys::svc('Bot')->talk($bot, $chatId, $body);
            }
        }
        else
        {
            \Sys::svc('Smtp')->setupThread(\Auth::user()->id, $chatId, $message->id);
            $res = \Sys::svc('Smtp')->send($chatId, $body, \Input::data('subject'), $files);
        }

        return true; // $res;
    }

    /**
     * Finds a message with given text within a specified Chat
     *
     * @doc-var     (int) chatId        - Chat ID.
     * @doc-var     (string) text       - Text extract to find by.
     *
     * @throws \Exception
     */
    static public function findByText()
    {
        if (!$chatId = \Input::data('chatId'))
        {
            throw new \Exception('Chat ID not provided.');
        }
        if (!$text = \Input::data('text'))
        {
            throw new \Exception('Text is not provided.');
        }

        $text = '%' . $text . '%';

        return \DB::rows('SELECT * FROM message WHERE chat_id=? AND body LIKE ?', [$chatId, $text]);
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
        // TODO: create a separate faster function for QS
        foreach (\Sys::svc('Chat')->findAllByUserId(\Auth::user()->id, $filters, 'lastTs') as $chat)
        {
            if ($message = \Sys::svc('Message')->getLastByChatId($chat->id))
            {
                $items[] = array
                (
                    'id'        => $message->id,
                    'body'      => $message->body,
                    'subject'   => $message->subject,
                    'files'     => $message->files,
                    'ts'        => $message->ts,
                    'fromId'    => $message->user_id,
                    'refId'     => $message->ref_id,
                    'chatId'    => $chat->id,               // the chat will already be in list, so just get all the data from there
                );
            }
        }

        return $items;
    }
}
