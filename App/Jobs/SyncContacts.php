<?php

namespace App\Jobs;

use MongoDB\BSON\ObjectID;
use \App\Service\User as UserSvc;
use \App\Service\Message as MessageSvc;

/**
 * This is basically called only one time -- when user is connected to Context
 *
 * Class SyncContacts
 * @package App\Jobs
 */
class SyncContacts extends Generic
{
    public function testSetup()
    {
        $user = UserSvc::findOne(['email' => 'hollo.email@gmail.com']);

        $this->args = array
        (
            'user_id' => $user->_id,
        );
    }

    public function perform()
    {
        if (!$user = UserSvc::findOne(['_id' => new ObjectID($this->args['user_id'])]))
        {
            echo "No user found (ID = {$this->args['user_id']}).\n";
            return false;
        }

        if (!$user->roles)
        {
            echo "No user role for ID = {$user->_id}. Is this user real?\n";
            return false;
        }

        sleep(5);

        // subscribe for future updates
        UserSvc::subscribeToGmail($user);

        // fetch all the messages
        MessageSvc::syncAllByUserId($user, false);

        // fetch avatars
        UserSvc::updateAvatars($user);

        return true;
    }
}
