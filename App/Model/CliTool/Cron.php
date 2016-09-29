<?php

namespace App\Model\CliTool;

/**
 * Class Cron
 * @package App\Model\CliTool
 */
class Cron
{
    /**
     * @param bool $byTime
     * @param bool $byCount
     * @return string
     */
    public function removeOldMessages($byTime = true, $byCount = false)
    {
        $count = 0;

        // This call is too heavy!
        // We must not run this in production

        // TODO: report every 1000 chats

        foreach (\Sys::svc('Chat')->findAll() as $chat)
        {
            $count += \Sys::svc('Message')->removeOld($chat->id, $byTime, $byCount);
        }

        return "Messaged purged: $count\n";
    }
}
