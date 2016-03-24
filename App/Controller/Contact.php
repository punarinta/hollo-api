<?php

namespace App\Controller;

class Contact extends Generic
{
    static public function find()
    {
        // TODO: support filtering byt email and subject
        // TODO: sort by last activity (desc ts)

        $items = [];

        foreach (\Sys::svc('Contact')->findForMe() as $row)
        {
            $items[] = array
            (
                'name'  => isset ($row['name']) ? $row['name'] : $row['email'],
                'email' => $row['email'],
            );
        }

        return $items;
    }
}
