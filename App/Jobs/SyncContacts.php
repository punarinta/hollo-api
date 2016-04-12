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

        \Sys::svc('Contact')->syncAll($user->id, true);

        // end sync transaction
        $user->is_syncing = 0;
        \Sys::svc('User')->update($user);

        return true;
    }
}
