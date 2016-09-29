<?php

namespace App\Model\CliTool;

/**
 * Class Cron
 * @package App\Model\CliTool
 */
class Cron
{
    public function removeOldMessages()
    {
        $count = 0;

        // This call is too heavy!
        // We must not run this in production

        foreach (\Sys::svc('Chat')->findAll() as $chat)
        {
            $count += \Sys::svc('Message')->removeOld($chat->id);
        }

        return "Messaged purged: $count\n";
    }
}
