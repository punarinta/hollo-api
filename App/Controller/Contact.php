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
     * Lists all your contacts
     *
     * @return array
     */
    static public function find()
    {
        $users = [];

        foreach (\Sys::svc('User')->findKnownBy(\Auth::user()->id, \Input::data('filters') ?: []) as $user)
        {
            $users[$user->id] = array
            (
                'id'    => $user->id,
                'email' => $user->email,
                'name'  => $user->name,
            );
        }

        return array_values($users);
    }
}
