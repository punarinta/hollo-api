<?php

namespace App\Controller;

class Settings extends Generic
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
            'flags'     => $settings->flags,
        );
    }
    
    /**
     * Update your profile info
     *
     * @doc-var     (string) firstName      - First name.
     * @doc-var     (string) lastName       - Last name.
     * @doc-var     (string) signature      - Signature.
     * @doc-var     (object) flag           - Set an arbitrary flag.
     * @doc-var     (string) flag.name      - Flag name.
     * @doc-var     (bool) flag.value       - Flag status.
     *
     * @throws \Exception
     */
    static public function update()
    {
        $user = \Auth::user();
        $settings = json_decode($user->settings, true);

        if (\Input::data('firstName') !== null) $settings['firstName'] = \Input::data('firstName');
        if (\Input::data('lastName') !== null) $settings['lastName'] = \Input::data('lastName');
        if (\Input::data('signature') !== null) $settings['signature'] = \Input::data('signature');

        if ($flag = \Input::data('flag'))
        {
            if (!isset ($settings['flags']))
            {
                $settings['flags'] = [];
            }

            $settings['flags'][$flag['name']] = $flag['value'];
        }

        // save settings back
        $user->settings = json_encode($settings);
        \Sys::svc('User')->update($user);
        \Sys::svc('Auth')->sync();

        return $settings;
    }
}
