<?php

namespace App\Model\CliTool;

/**
 * Class Cron
 * @package App\Model\CliTool
 */
class Cron
{
    /**
     * @return string
     */
    public function removeOldMessages()
    {
        $users = 0;
        $count = 0;

        foreach (\Sys::svc('User')->findAllReal() as $user)
        {
            ++$users;
            $count += \Sys::svc('Message')->removeOldByRefId($user->id);

            if (!($users % 100))
            {
                echo "Purged: $count\n";
            }
        }

        return "Total messaged purged: $count\n";
    }
}
