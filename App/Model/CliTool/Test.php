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
        \Sys::svc('Smtp')->setupThread(1, 1);
        \Sys::svc('Smtp')->send([], 'hollo, world!');
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

    public function scope()
    {
        require 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php';

        $client = new \Google_Client();
        $client->setApplicationName('Hollo app');
        $client->setClientId(\Sys::cfg('social_auth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('social_auth.google.secret'));
        $client->setRedirectUri('https://api.context.io/connect/oauth2callback');
        $client->addScope('https://mail.google.com/');
        $client->setIncludeGrantedScopes(true);
        $client->setAccessType('offline');

        //echo $client->createAuthUrl();

        $service = new \Google_Service_Gmail($client);

        $client->authenticate('4/H4JGXcqD0qAybX1e-rMMK-_nuRUQoIxVbkGolZPtJKU', true);
        $token = $client->getAccessToken();
        echo "Token: $token\n";

    /*    $mail = new \PHPMailer;
        $mail->SMTPDebug = 3;

        $mail->oauthUserEmail = 'hollo.email@gmail.com';
        $mail->oauthClientId = \Sys::cfg('social_auth.google.clientId');
        $mail->oauthClientSecret = \Sys::cfg('social_auth.google.secret');
        $mail->oauthRefreshToken = $token;

        $mail->send();*/

        return "\n";
    }
}
