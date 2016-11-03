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

                        // inform in any case
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

    /**
     * Update GMail subscriptions
     *
     * @return string
     */
    public function refreshGmailSubscription()
    {
        $count = 0;

        foreach (\Sys::svc('User')->findAllReal() as $user)
        {
            echo "User {$user->id}... ";
            $res = \Sys::svc('User')->subscribeToGmail($user);

            echo ($res ? 'OK' : 'NOT OK') . "\n";

            $count += 1 * $res;
        }

        return "Updated $count subscriptions.\n";
    }

    /**
     * Resync avatars for all the users
     *
     * @param null $userId
     * @return string
     */
    public function updateAvatars($userId = null)
    {
        $countAvas = 0;
        $countUsers = 0;

        $users = $userId ? [\Sys::svc('User')->findById($userId)] : \Sys::svc('User')->findAllReal();

        foreach ($users as $user)
        {
            echo "\nUser {$user->id}... ";

            $countAvas += \Sys::svc('User')->updateAvatars($user);

            ++$countUsers;
        }

        return "Updated $countAvas avatars for $countUsers users.\n";
    }
}
