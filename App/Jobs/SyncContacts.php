<?php

namespace App\Jobs;

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

        if (!$user->roles)
        {
            echo "No user role for ID = {$user->id}. Is this user real?\n";
            return false;
        }

        sleep(5);

        // subscribe for future updates
        \Sys::svc('User')->subscribeToGmail($user);

        // fetch all the messages
        \Sys::svc('Message')->syncAllByUserId($user->id, false);

        // fetch avatars
        \Sys::svc('User')->updateAvatars($user);

        return true;
    }
}
