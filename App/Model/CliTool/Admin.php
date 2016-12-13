<?php

namespace App\Model\CliTool;

use App\Model\Bcrypt;
use App\Model\Inbox\Gmail;
use \App\Service\User as UserSvc;
use \App\Service\Message as MessageSvc;

/**
 * Class Admin
 * @package App\Model\CliTool
 */
class Admin
{
    public function sync($userId)
    {
        $x = MessageSvc::syncAllByUserId($userId);

        return "Messages synced: $x\n\n";
    }

    public function syncMessage($userId, $messageExtId)
    {
        MessageSvc::sync($userId, $messageExtId);

        return '';
    }

    public function prepareSync($showMails = 0)
    {
        foreach (UserSvc::findAllReal() as $user)
        {
            $suffix = $showMails ? " ({$user->email})" : '&';
            echo "php cli admin sync {$user->_id} $suffix\n";
        }
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
