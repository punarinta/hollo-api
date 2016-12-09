<?php

namespace App\Jobs;

use MongoDB\BSON\ObjectID;
use \App\Service\User as UserSvc;
use \App\Service\Notify as NotifySvc;

/**
 * Class TestNotifications
 * @package App\Jobs
 */
class TestNotifications extends Generic
{
    public function testSetup()
    {
        $user = UserSvc::findOne(['email' => 'hollo.email@gmail.com']);

        $this->args = array
        (
            'user_id'   => $user->_id,
            'mode'      => 'firebase',
        );
    }

    public function perform()
    {
        if (!$user = UserSvc::findOne(['_id' => new ObjectID($this->args['user_id'])]))
        {
            echo "No user found (ID = {$this->args['user_id']}).\n";
            return false;
        }

        $res = 'Error';
        sleep(5);

        if ($this->args['mode'] == 'firebase')
        {
            $payload = array
            (
                'to'           => '/topics/user-' . $user->_id,
                'priority'     => 'high',

                'notification' => array
                (
                    'title' => 'You requested a test',
                    'body'  => 'Surprise! 🎂',
                    'icon'  => 'fcm_push_icon'
                ),

                'data' => array
                (
                    'cmd'    => 'sys:ping',
                    'authId' => $user->_id,
                ),
            );

            $res = NotifySvc::firebase($payload);
        }
        elseif ($this->args['mode'] == 'im')
        {
            $payload = array
            (
                'cmd'       => 'sys:ping',
                'userIds'   => [$user->_id],
            );

            $res = NotifySvc::im($payload);
        }

        print_r($res);

        return true;
    }
}
