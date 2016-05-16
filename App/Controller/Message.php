<?php

namespace App\Controller;

class Message extends Generic
{
    /**
     * Returns contacts messages
     *
     * @doc-var     (string) subject    - Filter by subject.
     * @doc-var     (bool) ninja        - Ninja mode, set 'true' to keep messages unread.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function findByEmail()
    {
        if (!$email = \Input::data('email'))
        {
            throw new \Exception('Email not provided.');
        }

        if (!$contact = \Sys::svc('Contact')->findByEmailAndUserId($email, \Auth::user()->id))
        {
            throw new \Exception('Contact not found.');
        }

        if (!\Input::data('ninja'))
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
            'messages'  => \Sys::svc('Message')->findByContactEmail($email, \Auth::user()->id, \Input::data('subject')),
        );
    }

    /**
     * Returns N more messages for a contact
     *
     * @doc-var     (string) subject     - Filter by subject.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function moreByEmail()
    {
        if (!$email = \Input::data('email'))
        {
            throw new \Exception('Email not provided.');
        }

        if (!$contact = \Sys::svc('Contact')->findByEmailAndUserId($email, \Auth::user()))
        {
            throw new \Exception('Contact not found');
        }

        return \Sys::svc('Message')->moreByContact($contact, \Auth::user());
    }

    /**
     * Reply to a message or compose a new one
     *
     * @doc-var     (string) subject        - Message subject.
     * @doc-var     (string) body           - Message body.
     * @doc-var     (int) messageId         - Hollo's message ID.
     *
     * @return bool
     * @throws \Exception
     */
    static public function send()
    {
        if (!$body = \Input::data('body'))
        {
            throw new \Exception('Body not provided.');
        }

        return true;
    }
}
