<?php

namespace App\Model\CliTool;

/**
 * Class Admin
 * @package App\Model\CliTool
 */
class Admin
{
    public function sync($userId)
    {
        $r = \Sys::svc('Contact')->syncAll($userId, true);

        echo "Contacts synced: {$r['contacts']}\n";
        echo "Messages synced: {$r['messages']}\n";

        return "\n";
    }
}
