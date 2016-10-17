<?php

namespace App\Model\CliTool;
use App\Model\Bcrypt;

/**
 * Class Admin
 * @package App\Model\CliTool
 */
class Admin
{
    public function sync($userId, $fetchMuted = false)
    {
        $x = \Sys::svc('Message')->syncAllByUserId($userId, $fetchMuted, true);

        return "Messages synced: $x\n\n";
    }

    public function syncMessage($userId, $messageExtId)
    {
        \Sys::svc('Message')->sync($userId, $messageExtId);

        return '';
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
