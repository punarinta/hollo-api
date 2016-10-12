<?php

namespace App\Model\Inbox;

/**
 * Class Imap
 * @package App\Model
 */
class Imap extends Generic implements InboxInterface
{
    private $password = null;

    /**
     * Init and get a password
     *
     * Google constructor.
     * @param $user
     */
    public function __construct($user)
    {
        if (!is_object($user))
        {
            $user = \Sys::svc('User')->findById($user);
        }

        $settings = json_decode($user->settings, true) ?: [];

        if (!$hash = $settings['hash'])
        {
            return;
        }
    }

    public function checkNew($userId)
    {
    }

    public function getMessages()
    {
    }

    public function getMessage($messageId)
    {
    }
}
