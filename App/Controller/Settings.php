<?php

namespace App\Controller;

use \App\Service\Auth as AuthSvc;
use \App\Service\User as UserSvc;
use \App\Service\Resque as ResqueSvc;

/**
 * Class Settings
 * @package App\Controller
 * @doc-api-path /api/settings
 */
class Settings extends Generic
{
    /**
     * Returns information about your profile
     *
     * @return array
     */
    static public function read()
    {
        $settings = \Auth::user()->settings;
       
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
        $settings = $user->settings;

        if (\Input::data('firstName') !== null) $settings->firstName = \Input::data('firstName');
        if (\Input::data('lastName') !== null) $settings->lastName = \Input::data('lastName');
        if (\Input::data('signature') !== null) $settings->signature = \Input::data('signature');

        if ($flag = \Input::data('flag'))
        {
            $settings->flags->{$flag['name']} = $flag['value'];
        }

        // save settings back
        UserSvc::update($user, ['settings' => $settings]);
        AuthSvc::sync();

        return $settings;
    }

    static public function testNotification()
    {
        $mode = \Input::data('mode') ?: 'firebase';

        ResqueSvc::addJob('TestNotifications', ['user_id' => \Auth::user()->_id, 'mode' => $mode ]);
    }
}
