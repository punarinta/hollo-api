<?php

namespace App\Controller;

class Message extends Generic
{
    /**
     * Returns contacts messages
     *
     * @doc-var     (string) subject     - Filter by subject.
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

        return array
        (
            'contact'   => array
            (
                'id'    => $contact->id,
                'name'  => $contact->name,
                'muted' => $contact->muted,
            ),
            'messages'  => \Sys::svc('Message')->findByContactEmail($email, \Input::data('subject')),
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
     * @return bool
     */
    static public function reply()
    {
        return true;
    }
}
