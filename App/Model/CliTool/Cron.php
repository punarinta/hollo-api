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

        foreach (\Sys::svc('Chat')->findAll() as $chat)
        {
            $count += \Sys::svc('Message')->removeOld($chat->id);
        }

        return "Messaged purged: $count\n";
    }
}
