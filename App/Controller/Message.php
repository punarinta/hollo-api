<?php

namespace App\Controller;

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

        if (!\Sys::svc('Chat')->hasAccess($chatId, \Auth::user()->id))
        {
            throw new \Exception('Access denied.', 403);
        }

        if (!$chat = \Sys::svc('Chat')->findById($chatId))
        {
            throw new \Exception('Chat does not exist.');
        }

        $msgs = \Sys::svc('Message')->findByChatId($chat->id, \Input::data('subject'));
        $flags = \Sys::svc('Chat')->getFlags($chat->id, \Auth::user()->id);

        if ($msgs && !\Input::data('ninja'))
        {
            $flags->read = 1;
            \Sys::svc('Chat')->setFlags($chatId, \Auth::user()->id, $flags);
        }

        return array
        (
            'chat'   => array
            (
                'id'    => $chat->id,
                'name'  => $chat->name,
                'muted' => $flags->muted,
                'users' => \Sys::svc('User')->findByChatId($chat->id, true, \Auth::user()->id),
            ),
            'messages'  => $msgs,
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

        if (!$data = \Sys::svc('Message')->getDataByExtId(\Auth::user()->ext_id, $message->ext_id))
        {
            return false;
        }

        return $data['body'][0]['content'];
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
            throw new \Exception('Recipient list is not provided.');
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

        \Sys::svc('Smtp')->setupThread(\Auth::user()->id, \Input::data('messageId'), $message->id);
        $res = \Sys::svc('Smtp')->send($chatId, $body, \Input::data('subject'), $files);

        // force Context.IO sync after mail is sent
        \Sys::svc('User')->syncExt(\Auth::user());

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
}
