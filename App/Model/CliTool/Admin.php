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

        return "Messages synced: $x\n\n";
    }

    public function syncMessage($accountId, $messageExtId)
    {
        \Sys::svc('Message')->sync($accountId, $messageExtId, true);
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
