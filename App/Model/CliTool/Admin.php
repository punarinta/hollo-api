<?php

namespace App\Model\CliTool;
use App\Model\Bcrypt;
use App\Model\Inbox\Gmail;

/**
 * Class Admin
 * @package App\Model\CliTool
 */
class Admin
{
    public function sync($userId, $fetchMuted = false)
    {
        $x = \Sys::svc('Message')->syncAllByUserId($userId, $fetchMuted);

        return "Messages synced: $x\n\n";
    }

    public function syncMessage($userId, $messageExtId)
    {
        \Sys::svc('Message')->sync($userId, $messageExtId);

        return '';
    }

    /**
     * @param $userId
     * @param $historyId
     * @param null $labelId
     * @return string
     */
    public function showHistory($userId, $historyId, $labelId = null)
    {
        $inbox = new Gmail($userId);

        print_r($inbox->listHistory($historyId, $labelId));

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
