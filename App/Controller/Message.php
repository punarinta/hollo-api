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
                'users' => \Sys::svc('User')->findByChatId($chat->id, true),
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

        if (!$chat = \Sys::svc('Chat')->findById($chatId))
        {
            throw new \Exception('Chat does not exist.');
        }

        return \Sys::svc('Message')->moreByChat($chat, \Auth::user());
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
     * @doc-var     (int) messageId         - Hollo's message ID to reply to.
     * @doc-var     (array) to              - Array of extra recipient emails. Required if no messageId given.
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

        if (!$to = \Input::data('to'))
        {
            throw new \Exception('Recipient list is not provided.');
        }

        \Sys::svc('Smtp')->setupThread(\Auth::user()->id, \Input::data('messageId'));
        $res = \Sys::svc('Smtp')->send($to, $body, \Input::data('subject'), $files);

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
