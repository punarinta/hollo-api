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
        if (!$userId = (int) $this->args['user_id'])
        {
            return false;
        }

        \Sys::svc('Contact')->syncAll($userId);

        return false;
    }
}
