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
                        $count += 1 * !empty(\Sys::svc('Message')->sync($user->id, $messageId, false, ['noMarks' => true]));

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

    public function refreshGmailSubscription()
    {
        foreach (\Sys::svc('User')->findAllReal() as $user)
        {
            $settings = json_decode($user->settings, true) ?: [];

            if ($settings['svc'] != 1)
            {
                continue;
            }

            echo "User {$user->id}... ";

            if (!$token = $settings['token'])
            {
                echo "No refresh token\n";
                continue;
            }

            $client = new \Google_Client();
            $client->setClientId(\Sys::cfg('oauth.google.clientId'));
            $client->setClientSecret(\Sys::cfg('oauth.google.secret'));
            $client->refreshToken($token);
            $accessToken = $client->getAccessToken();
            $accessToken = json_decode($accessToken, true);

            $ch = curl_init("https://www.googleapis.com/gmail/v1/users/me/watch?oauth_token=" . $accessToken['access_token']);

            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array
            (
                'topicName' => 'projects/hollo-1271/topics/gmail',
                'labelIds'  => ['INBOX'],
            )));
            $res = curl_exec($ch);
            curl_close($ch);

            print_r($res);

            echo "OK\n";
        }

        return '';
    }
}
