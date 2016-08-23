<?php

namespace App\Model\CliTool;
use App\Model\Bcrypt;

/**
 * Class Admin
 * @package App\Model\CliTool
 */
class Admin
{
    public function sync($userId, $fetchMuted = false, $fetchAll = false)
    {
        $x = \Sys::svc('Message')->syncAllByUserId($userId, $fetchMuted, $fetchAll);

        echo "Messages synced: $x\n";

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

        return "Hash: '$hash'\n";
    }
}
