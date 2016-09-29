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

        if (!$user->ext_id)
        {
            echo "No account connected. User ID = {$user->id}\n";
            return false;
        }

        // use force when syncing for the first time
        \Sys::svc('Message')->syncAllByUserId($user->id);

        return true;
    }
}
