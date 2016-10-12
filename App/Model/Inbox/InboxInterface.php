<?php

namespace App\Model\Inbox;

/**
 * Interface InboxInterface
 * @package App\Model\Imap
 */
interface InboxInterface
{
    public function checkNew($userId);
    public function getMessages();
    public function getMessage($messageId);
}
