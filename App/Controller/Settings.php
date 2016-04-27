<?php

namespace App\Controller;

class Profile extends Generic
{
    /**
     * Returns information about your profile
     *
     * @return array
     */
    static public function read()
    {
        $settings = json_decode(\Auth::user()->settings);
       
        return array
        (
            'firstName' => $settings->firstName,
            'lastName'  => $settings->lastName,
            'signature' => $settings->signature,
        );
    }
    
    /**
     * Update your profile info
     *
     * @doc-var     (int) id!               - Contact ID.
     * @doc-var     (string) firstName      - First name.
     * @doc-var     (string) lastName       - Last name.
     *
     * @throws \Exception
     */
    static public function update()
    {
        $user = \Auth::user();
        $settings = json_decode($user->settings);

        if (\Input::data('firstName') !== null) $settings->firstName = \Input::data('firstName');
        if (\Input::data('lastName') !== null) $settings->lastName = \Input::data('lastName');

        \Sys::svc('User')->update($user);
    }
}