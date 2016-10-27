<?php

namespace App\Jobs;

/**
 * Class TestNotifications
 * @package App\Jobs
 */
class TestNotifications extends Generic
{
    public function testSetup()
    {
        $this->args = array
        (
            'user_id' => 1,
        );
    }

    public function perform()
    {
        if (!$user = \Sys::svc('User')->findById($this->args['user_id']))
        {
            echo "No user found (ID = {$this->args['user_id']}).\n";
            return false;
        }

        $payload = array
        (
            'to'           => '/topics/user-' . $user->id,
            'priority'     => 'high',

            'notification' => array
            (
                'title' => 'You requested a test',
                'body'  => 'Surprise! 🎂',
                'icon'  => 'fcm_push_icon'
            ),

            'data' => array
            (
                'authId' => $user->id,
                'cmd'    => 'ping',
            ),
        );

        sleep(5);
        $res = \Sys::svc('Notify')->firebase($payload);
        print_r($res);

        return true;
    }
}
