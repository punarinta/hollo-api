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
     * @return string
     */
    public function updateAvatars()
    {
        $countAvas = 0;
        $countUsers = 0;

        foreach (\Sys::svc('User')->findAllReal() as $user)
        {
            $settings = json_decode($user->settings, true) ?: [];

            if (!$token = $settings['token'])
            {
                return "No refresh token\n";
            }

            $client = new \Google_Client();
            $client->setClientId(\Sys::cfg('oauth.google.clientId'));
            $client->setClientSecret(\Sys::cfg('oauth.google.secret'));
            $client->refreshToken($token);
            $accessToken = $client->getAccessToken();
            $accessToken = json_decode($accessToken, true);

            $pageSize = 25;
            $pageStart = 1;

            echo "Access token = {$accessToken['access_token']}\n\n";

            while ($pageStart < 5000)   // limit just to be safe
            {
                echo "PageStart = $pageStart\n";

                $ch = curl_init("https://www.google.com/m8/feeds/contacts/default/full?start-index=$pageStart&max-results=$pageSize&alt=json&v=3.0&oauth_token=" . $accessToken['access_token']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $contacts = json_decode(curl_exec($ch), true) ?:[];
                curl_close($ch);

                foreach ($contacts['feed']['entry'] as $contact)
                {
                    $email = $contact['gd$email'][0]['address'];

                    if (file_exists('data/files/avatars/' . $email))
                    {
                        continue;
                    }

                    $image = null;

                    if (isset ($contact['link'][0]['href']))
                    {
                        $url = $contact['link'][0]['href'];
                        $url = $url . '&access_token=' . urlencode($accessToken['access_token']);

                        $curl = curl_init($url);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($curl, CURLOPT_TIMEOUT, 3);
                        $image = curl_exec($curl);
                        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                        curl_close($curl);

                        if ($httpcode == 200 && isset ($contact['gd$email'][0]['address']))
                        {
                            /* $results[] = array
                            (
                                'name'  => $contact['title']['$t'],
                                'email' => $contact['gd$email'][0]['address'],
                                'image' => base64_encode($image),
                            ); */

                            echo "Saving ava for $email...\n";
                            file_put_contents('data/files/avatars/' . $email, $image);

                            ++$countAvas;
                        }
                    }
                }

                if (count($contacts['feed']['entry']) < $pageSize)
                {
                    break;
                }
                else
                {
                    $pageStart += $pageSize;
                }
            }

            ++$countUsers;
        }

        return "Updated $countAvas avatars for $countUsers users.\n";
    }
}
