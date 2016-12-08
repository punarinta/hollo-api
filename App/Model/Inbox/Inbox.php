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
     * @param $user
     * @return InboxInterface
     * @throws \Exception
     */
    public static function init($user)
    {
        // TODO: memorize and check next time if already initialized for this user

        if (!is_object($user))
        {
            if (!$user = UserSvc::findOne(['_id' => new ObjectID($user)]))
            {
                throw new \Exception('User does not exist. ID = ' . $user);
            }
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
