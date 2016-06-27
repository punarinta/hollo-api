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

        foreach (\Sys::svc('Contact')->findAll() as $contact)
        {
            $count += \Sys::svc('Message')->removeOld($contact->id);
        }

        return "Messaged purged: $count\n";
    }
}
