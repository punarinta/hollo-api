<?php

namespace App\Controller;

class Message extends Generic
{
    static public function findByEmail()
    {
        if (!$email = \Input::data('email'))
        {
            throw new \Exception('Email not provided.');
        }

        return \Sys::svc('Message')->findByContact($email);
    }

    static public function reply()
    {
        return true;
    }
}
