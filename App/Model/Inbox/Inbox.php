<?php

namespace App\Model\Inbox;

use MongoDB\BSON\ObjectID;
use \App\Service\User as UserSvc;
use \App\Service\MailService as MailServiceSvc;

class Inbox
{
    /**
     * Initializes a mail layer class for a user
     *
     * @param $userId
     * @return InboxInterface
     * @throws \Exception
     */
    public static function init($userId)
    {
        // TODO: memorize and check next time if already initialized for this user

        if (!is_object($userId))
        {
            if (!$user = UserSvc::findOne(['_id' => new ObjectID($userId)]))
            {
                throw new \Exception('User does not exist. ID = ' . $userId);
            }
        }
        else
        {
            $user = $userId;
        }

        if (!$svc = $user->settings->svc)
        {
            throw new \Exception('No service ID for user. ID = ' . $user->_id);
        }

        if ($svc == MailServiceSvc::findOne(['name' => 'Gmail'])->_id)
        {
            $class = '\App\Model\Inbox\Gmail';
        }
        else
        {
            $class = '\App\Model\Inbox\Imap';
        }

        return new $class($user);
    }
}
