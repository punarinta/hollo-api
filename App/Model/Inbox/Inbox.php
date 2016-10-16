<?php

namespace App\Model\Inbox;

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
            if (!$user = \Sys::svc('User')->findById($user))
            {
                throw new \Exception('User does not exist. ID = ' . $user);
            }
        }

        if (!$svc = \Sys::aPath(json_decode(@$user->settings, true) ?:[], 'svc'))
        {
            throw new \Exception('Cannot initialize mail layer for user. ID = ' . $user->id);
        }

        if ($svc == 1)
        {
            $class = '\App\Model\Inbox\Gmail';
        }
        else
        {
            $class = '\App\Model\Inbox\Imap';
        }

        return new $class($user->id);
    }
}
