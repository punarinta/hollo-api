<?php

namespace App\Controller;

class GmailPush
{
    /**
     * @return mixed
     * @throws \Exception
     */
    static public function index()
    {
        file_put_contents('data/files/pubsub.log', "Incoming...\n", FILE_APPEND);

        $json = json_decode(file_get_contents('php://input'), 1);

        if (isset ($json['message']['data']))
        {
            $x = json_decode(base64_decode($json['message']['data']));
            $user = \Sys::svc('User')->findByEmail($x['emailAddress']);

            $messageId = $json['message']['message_id'];
            $json['user_id'] = $user->id;
        }

        file_put_contents('data/files/pubsub.log', json_encode($json, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        return true;
    }
}