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
    public function updateSource()
    {
        $user = \Sys::svc('User')->findById(1);

        $cfg = \Sys::cfg('contextio');
        $conn = new ContextIO($cfg['key'], $cfg['secret']);

        $res = $conn->post($user->ext_id, 'sources/0', array
        (
            'status'                    => 'DISABLED',
            'provider_refresh_token'    => 'hello world',
        ));

        print_r($res->getData());
    }

    public function smtp()
    {
        // fill your own refresh token
        $_SESSION['-AUTH']['mail']['token'] = 'foobar';
        \Sys::svc('Smtp')->setupThread(1, 1);
        \Sys::svc('Smtp')->send([['email'=>'cheaterx@yandex.ru', 'name'=>'Test Guy']], 'hollo, world!' . uniqid());
        return "\n";
    }

    public function gmail()
    {
        $client = new \Google_Client();
        $client->setApplicationName('Hollo app');
        $client->setClientId(\Sys::cfg('social_auth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('social_auth.google.secret'));
        $client->setRedirectUri('https://app.hollo.dev/oauth/google');
        $client->addScope('https://mail.google.com/');
        $client->addScope('https://www.googleapis.com/auth/userinfo.email');
        $client->addScope('https://www.googleapis.com/auth/plus.me');
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        // Load previously authorized credentials from a file.
        $credentialsPath = 'google.token';
        if (file_exists($credentialsPath))
        {
            $accessToken = file_get_contents($credentialsPath);
        }
        else
        {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->authenticate($authCode);

            // Store the credentials to disk.
            file_put_contents($credentialsPath, $accessToken);
            printf("Credentials saved to %s\n", $credentialsPath);
        }
        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired())
        {
            $client->refreshToken($client->getRefreshToken());
            file_put_contents($credentialsPath, $client->getAccessToken());
        }

        echo "Refresh token: " . $client->getRefreshToken() . "\n";
        echo "Access token: " . $accessToken . "\n";

        // use an access token to fetch email and picture
        $accessToken = json_decode($accessToken, true);

        $ch = curl_init('https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $accessToken['access_token']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = json_decode(curl_exec($ch), true) ?:[];
        curl_close($ch);

        // @$data['picture'];   — url of an avatar

        echo "Email: {$data['email']}\n";

        return "\n";
    }

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

    public function inbox($userId = 1)
    {
        $imap = new Imap($userId); // 11298 - attachment, 11216 - polish symbols

    //    print_r($imap->getMessages(['ts_after' => strtotime('2016-10-14')]));

        print_r($imap->getMessage(11077));

    //    $imap = new Gmail($userId);
    //    print_r($imap->getMessage('155b48e12ef23f00'));

        return '';
    }
}
