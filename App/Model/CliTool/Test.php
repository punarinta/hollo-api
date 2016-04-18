<?php

namespace App\Model\CliTool;

/**
 * Class Test
 * @package App\Model\CliTool
 */
class Test
{
    public function scope()
    {
        require 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php';

        $client = new \Google_Client();
        $client->setApplicationName('Hollo app');
        $client->setClientId(\Sys::cfg('social_auth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('social_auth.google.secret'));
        $client->setRedirectUri('https://app.hollo.dev');
        $client->setScopes(array('https://mail.google.com/'));
        $client->setAccessType('offline');

        $client->authenticate('fpvrcxnrpjn612gc');
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
