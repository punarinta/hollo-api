<?php

namespace App\Model\CliTool;
use App\Model\Bcrypt;

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
    
    public function hashpass($password = null)
    {
        if (!$password)
        {
            return "No password provided\n";
        }

        $crypt = new Bcrypt;
        $hash = $crypt->create($password);

        return "Hash: '$hash'\n\n";
    }
}
