<?php

namespace App\Jobs;

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

        if ($user->is_syncing)
        {
            // just skip this run
            return true;
        }

        if (!$user->ext_id)
        {
            echo "No account connected. User ID = {$user->id}\n";
            return false;
        }

        // use force when syncing for the first time
        \Sys::svc('Contact')->syncAll($user->id, true, $user->last_sync_ts == 1);

        return true;
    }
}
