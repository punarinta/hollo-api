<?php

namespace App\Model\CliTool;
use App\Model\Inbox\Inbox;

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

    /**
     * @param int $type     -- TYPE=1 for Google, TYPE=2 for everything else
     * @return string
     */
    public function checkNewMails($type = 1)
    {
        $tsAfter = strtotime('yesterday');

        foreach (\Sys::svc('User')->findAllReal() as $user)
        {
            microtime(100000);

            $svc = json_decode($user->settings)->svc;

            if (($type == 1 && $svc != 1) || ($type != 1 && $svc == 1))
            {
                continue;
            }

            $inbox = Inbox::init($user);

            if ($inbox->checkNew())
            {
                $count = 0;
                echo "User {$user->id} has new messages. Syncing... ";

                $messageIds = $inbox->getMessages(['ts_after' => $tsAfter]);

                if (count($messageIds))
                {
                    $lastMuid = $messageIds[0];

                    foreach ($messageIds as $messageId)
                    {
                        if ($messageId == $user->last_muid)
                        {
                            // we've reached
                            break;
                        }

                        $count += 1 * !empty(\Sys::svc('Message')->sync($user->id, $messageId, false));

                        usleep(200000);
                    }

                    if ($user->last_muid != $lastMuid)
                    {
                        $user->last_muid = $lastMuid;
                        \Sys::svc('User')->update($user);
                    }
                }

                echo "Found $count new.\n";
            }
        }

        return '';
    }
}
