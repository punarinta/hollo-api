<?php

namespace App\Controller;

class Contact extends Generic
{
    static public function find()
    {
        // TODO: support filtering byt email and subject
        // TODO: sort by last activity (desc ts)

        return \Sys::svc('Contact')->findForMe();
    }

    static public function sync()
    {
        \Sys::svc('Contact')->sync();
    }
}
