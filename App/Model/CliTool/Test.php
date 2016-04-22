<?php

namespace App\Model\CliTool;

/**
 * Class Test
 * @package App\Model\CliTool
 */
class Test
{
    public function smtp()
    {
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
}
