<?php

namespace App\Model\Inbox;

use MongoDB\BSON\ObjectID;

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
            if (!$user = \Sys::svc('User')->findOne(['_id' => new ObjectID($user)]))
            {
                throw new \Exception('User does not exist. ID = ' . $user);
            }
        }

        if (!$svc = $user->settings->svc)
        {
            throw new \Exception('Cannot initialize mail layer for user. ID = ' . $user->_id);
        }

        if ($svc == \Sys::svc('MailService')->findOne(['name' => 'Gmail'])->_id->__toString())
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
