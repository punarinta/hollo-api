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

    public function syncMessage($userId, $messageExtId)
    {
        if (!$user = \Sys::svc('User')->findById($userId))
        {
            return "User not found: $userId\n";
        }
        \Sys::svc('Message')->sync($user->ext_id, $messageExtId);

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
