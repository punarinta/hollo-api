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
     * Returns contacts messages
     *
     * @doc-var     (int) id            - Contact's ID.
     * @doc-var     (string) email      - Contact's email. Needed if no email provided.
     * @doc-var     (string) subject    - Filter by subject.
     * @doc-var     (bool) ninja        - Ninja mode, set 'true' to keep messages unread.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function findByReference()
    {
        if (!$id = \Input::data('id'))
        {
            if (!$email = \Input::data('email'))
            {
                throw new \Exception('Email not provided.');
            }

            if (!$contact = \Sys::svc('Contact')->findByEmailAndUserId($email, \Auth::user()->id))
            {
                throw new \Exception('Contact not found.');
            }
        }
        elseif (!$contact = \Sys::svc('Contact')->findByIdAndUserId($id, \Auth::user()->id))
        {
            throw new \Exception('Contact not found.');
        }

        $msgs = \Sys::svc('Message')->findByContactId($contact->id, \Input::data('subject'));

        if ($msgs && !\Input::data('ninja'))
        {
            $contact->read = 1;
            \Sys::svc('Contact')->update($contact);
        }

        return array
        (
            'contact'   => array
            (
                'id'    => $contact->id,
                'name'  => $contact->name,
                'muted' => $contact->muted,
            ),
            'messages'  => $msgs,
        );
    }

    /**
     * Returns N more messages for a contact
     *
     * @doc-var     (int) contactId!        - Contact's ID
     *
     * @return mixed
     * @throws \Exception
     */
    static public function moreByContactId()
    {
        if (!$contactId = \Input::data('contactId'))
        {
            throw new \Exception('Email not provided.');
        }

        if (!$contact = \Sys::svc('Contact')->findByIdAndUserId($contactId, \Auth::user()->id))
        {
            throw new \Exception('Contact not found');
        }

        return \Sys::svc('Message')->moreByContact($contact, \Auth::user());
    }

    /**
     * Returns messages within a Chat
     *
     * @doc-var     (int) chatId        - Chat's ID.
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

        if ($msgs && !\Input::data('ninja'))
        {
            $chat->read = 1;
            \Sys::svc('Chat')->update($chat);
        }

        return array
        (
            'chat'   => array
            (
                'id'    => $chat->id,
                'name'  => $chat->name,
                'muted' => $chat->muted,
            ),
            'messages'  => $msgs,
        );
    }

    /**
     * Returns N more messages for a Chat
     *
     * @doc-var     (int) chatId!        - Chat's ID
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

        $to = \Input::data('to') ?: [];

        if (!$messageId = \Input::data('messageId'))
        {
            if (!$to)
            {
                throw new \Exception('Neither message ID nor recipient list is provided.');
            }
        }

        \Sys::svc('Smtp')->setupThread(\Auth::user()->id, $messageId);
        $res = \Sys::svc('Smtp')->send($to, $body, \Input::data('subject'), $files);

        // force Context.IO sync after mail is sent
        \Sys::svc('User')->syncExt(\Auth::user());

        return true; // $res;
    }
}
