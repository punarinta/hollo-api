<?php

namespace App\Model\CliTool;
use App\Model\ContextIO\ContextIO;

/**
 * Class Test
 * @package App\Model\CliTool
 */
class Test
{
    public function smtp()
    {
        \Sys::svc('Smtp')->setupThread(1, 1, 'subject');
        \Sys::svc('Smtp')->send([['email'=>'cheaterx@yandex.ru', 'name'=>'Tet Guy']], 'hollo, world!' . uniqid());
        return "\n";
    }

    public function move()
    {
        $cfg = \Sys::cfg('contextio');
        $conn = new ContextIO($cfg['key'], $cfg['secret']);

        $x = $conn->addMessageToFolder('57163f7e01f2b6954c8b456b', array
        (
            'dst_source' => 0,
            'dst_folder' => 'INBOX',
        //    'message' => file_get_contents('message.txt'),
            'src_file' => 'message.txt',
        ));

        print_r($x * 1);
    }

    public function gmail($code = null)
    {
        require 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php';

        $client = new \Google_Client();
        $client->setApplicationName('Hollo app');
        $client->setClientId(\Sys::cfg('social_auth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('social_auth.google.secret'));
        $client->setRedirectUri('https://app.hollo.dev/oauth/google');
        $client->addScope('https://mail.google.com/');
    //    $client->setIncludeGrantedScopes(true);
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

        echo "Refresh token: " . $client->getRefreshToken();

        return "\n";
    }
}
