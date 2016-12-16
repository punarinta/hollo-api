<?php

namespace App\Model\CliTool;
use App\Model\Inbox\Inbox;
use MongoDB\BSON\ObjectID;
use \App\Service\User as UserSvc;
use \App\Service\Chat as ChatSvc;
use \App\Service\Message as MessageSvc;
use \App\Service\MailService as MailServiceSvc;

/**
 * Class Cron
 * @package App\Model\CliTool
 */
class Cron
{
    /**
     * Removes old messages using service-layer algorithm
     *
     * @return string
     */
    public function removeOldMessages()
    {
        $steps = 0;
        $count = 0;

        foreach (ChatSvc::findAll([], ['projection' => ['messages' => 1]]) as $chat)
        {
            ++$steps;

            $messages = $chat->messages ?? [];

            if (!$preCount = count($messages))
            {
                continue;
            }

            $messages = MessageSvc::removeOld($messages);
            ChatSvc::update($chat, ['messages' => $messages]);
            $postCount = count($messages);

            $count += ($preCount - $postCount);

            if (!($steps % 100))
            {
                echo "Purged: $count\n";
            }
        }

        return "Total messages purged: $count\n";
    }

    /**
     * @param int $type     -- TYPE=1 for Google, TYPE=2 for everything else
     * @return string
     */
    public function checkNewMails($type = 1)
    {
        $tsAfter = strtotime('yesterday');

        $gmailSvcId = 'Gmail';
        if ($type == 1)
        {
            $gmailSvcId = MailServiceSvc::findOne(['name' => 'Gmail'])->_id;
        }

        foreach (UserSvc::findAllReal() as $user)
        {
            microtime(100000);

            $svc = $user->settings->svc;

            if (($type == 1 && $svc != $gmailSvcId) || ($type != 1 && $svc == $gmailSvcId))
            {
                continue;
            }

            $inbox = Inbox::init($user);

            if ($inbox->checkNew())
            {
                $count = 0;
                echo "User {$user->email} has new messages. Syncing... ";

                $messageIds = $inbox->getMessages(['ts_after' => $tsAfter]);

                if (count($messageIds))
                {
                    $lastMuid = $messageIds[0];

                    foreach ($messageIds as $messageId)
                    {
                        if ($messageId == $user->lastMuid)
                        {
                            // we've reached
                            break;
                        }

                        // inform in any case
                        $count += 1 * !empty (MessageSvc::sync($user, $messageId, false));

                        usleep(200000);
                    }

                    if ($user->lastMuid != $lastMuid)
                    {
                        UserSvc::update($user, ['lastMuid' => $lastMuid]);
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
     * @param null $userId
     * @return string
     */
    public function refreshGmailSubscription($userId = null)
    {
        $count = 0;
        $users = $userId ? [UserSvc::findOne(['_id' => new ObjectID($userId)])] : UserSvc::findAllReal();

        foreach ($users as $user)
        {
            if (!UserSvc::isMailSvc($user))
            {
                continue;
            }

            echo "User {$user->email}... ";
            $res = UserSvc::subscribeToGmail($user);

            echo ($res ? 'OK' : 'NOT OK') . "\n";

            $count += 1 * $res;
        }

        return "\nUpdated $count subscriptions.\n";
    }

    /**
     * Resync avatars for all the users
     *
     * @param null $userId
     * @param null $qEmail
     * @return string
     */
    public function updateAvatars($userId = null, $qEmail = null)
    {
        $countAvas = 0;
        $countUsers = 0;

        $users = $userId ? [UserSvc::findOne(['_id' => new ObjectID($userId)])] : UserSvc::findAllReal();

        foreach ($users as $user)
        {
            if (!UserSvc::isMailSvc($user))
            {
                continue;
            }

            echo "\nUser {$user->email}... ";

            $countAvas += UserSvc::updateAvatars($user, $qEmail);

            ++$countUsers;
        }

        return "\nUpdated $countAvas avatars for $countUsers users.\n";
    }
}
