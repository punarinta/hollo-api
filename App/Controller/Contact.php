<?php

namespace App\Controller;

/**
 * Class Contact
 * @package App\Controller
 * @doc-api-path /api/contact
 */
class Contact extends Generic
{
    /**
     * Extracts contacts from your chats
     *
     * @return array
     */
    static public function find()
    {
        // TODO: add pagination

        $users = [];

        foreach (\Sys::svc('User')->findKnownBy(\Auth::user()->id) as $user)
        {
            // TODO: check if manual distinct is necessary

            $users[$user->id] = array
            (
                'id'        => $user->id,
                'email'     => $user->email,
                'name'      => $user->name,
            );
        }

        return array_values($users);
    }
}
