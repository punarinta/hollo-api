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
        $profile = \Auth::profile();
       
        return array
        (
            'firstName' => $profile->first_name,
            'lastName'  => $profile->last_name,
            'signature' => null,
            'emails'    => \Sys::svc('Profile')->getEmailsForUser(\Auth::user()),
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
        $profile = \Auth::profile();

        if (\Input::data('firstName') !== null) $profile->first_name = \Input::data('firstName');
        if (\Input::data('lastName') !== null) $profile->last_name = \Input::data('lastName');

        \Sys::svc('Contact')->update($profile);
        \Sys::svc('Profile')->syncNames(\Auth::user()->id);
    }
}
