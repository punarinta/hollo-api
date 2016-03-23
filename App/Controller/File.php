<?php

namespace App\Controller;

class File extends Generic
{
    static public function findByEmail()
    {
        if (!$email = \Input::data('email'))
        {
            throw new \Exception('Email not provided.');
        }

        return \Sys::svc('Context')->listContactFiles($email);
    }
}
