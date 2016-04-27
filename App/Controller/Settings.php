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
     * @doc-var     (string) firstName      - First name.
     * @doc-var     (string) lastName       - Last name.
     * @doc-var     (string) signature      - Signature.
     *
     * @throws \Exception
     */
    static public function update()
    {
        $user = \Auth::user();
        $settings = json_decode($user->settings);

        if (\Input::data('firstName') !== null) $settings->firstName = \Input::data('firstName');
        if (\Input::data('lastName') !== null) $settings->lastName = \Input::data('lastName');
        if (\Input::data('signature') !== null) $settings->signature = \Input::data('signature');

        // save settings back
        $user->settings = json_encode($settings);
        \Sys::svc('User')->update($user);
        \Sys::svc('Auth')->sync();
    }
}
