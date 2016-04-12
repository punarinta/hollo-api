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

        return \Sys::svc('Message')->findByContact($email, \Input::data('subject'));
    }

    static public function reply()
    {
        return true;
    }
}
