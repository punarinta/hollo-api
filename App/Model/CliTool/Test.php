<?php

namespace App\Model\CliTool;

use App\Model\ContextIO\ContextIO;
use App\Model\Inbox\Gmail;
use App\Model\Inbox\Imap;

/**
 * Class Test
 * @package App\Model\CliTool
 */
class Test
{
    public function reClean()
    {
        $count = 0;

        foreach (\Sys::svc('Message')->findAll() as $message)
        {
            $count += 1 * \Sys::svc('Message')->reClean($message);
        }

        return "$count messages affected\n\n";
    }

    /**
     * @param int $userId
     * @return string
     */
    public function updateAvatars($userId = 1)
    {
        $user = \Sys::svc('User')->findById($userId);
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

                        $email = $contact['gd$email'][0]['address'];

                        echo "Saving ava for $email...\n";
                        file_put_contents('data/files/avatars/' . $email, $image);
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

        return "\n";
    }

    public function inbox($userId = 2)
    {
        $imap = new Imap($userId); // 11298 - attachment, 11216 - polish symbols

        print_r($imap->getMessages(['ts_after' => strtotime('2016-10-14')]));

    //    print_r($imap->getMessage(11077));

    //    $imap = new Gmail($userId);
    //    print_r($imap->getMessage('157c395527a42e95'));

        return '';
    }

    public function firebase()
    {
        $payload = array
        (
            'to'           => '/topics/user-1',
            'priority'     => 'high',

            'notification' => array
            (
                'title' => 'You have new message',          // Any value
                'body'  => 'Testing 🎂',                    // Any value
                'icon'  => 'fcm_push_icon'                  // White icon Android resource
            ),

            'data' => array
            (
                'authId' => 1,
                'cmd'    => 'show-chat',
                'chatId' => 1,
            ),
        );

        $res = \Sys::svc('Notify')->firebase($payload);

        print_r($res);
    }
}
