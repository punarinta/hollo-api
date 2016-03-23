<?php

namespace App\Controller;

class Contact extends Generic
{
    static public function findAll()
    {
        $items = [];

        foreach (\Sys::svc('Context')->listContacts() as $row)
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
