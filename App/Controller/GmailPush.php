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
        $json = json_decode(file_get_contents('php://input'), 1);

        // file_put_contents('data/files/pubsub.log', 'Payload: ' . json_encode($json) . "\n", FILE_APPEND);

        if (isset ($json['message']['data']))
        {
            $x = json_decode(base64_decode($json['message']['data']), true);
            $user = \Sys::svc('User')->findByEmail($x['emailAddress']);

            $messageId = $json['message']['message_id'];
            $json['user_id'] = $user->id;
        }

        file_put_contents('data/files/pubsub.log', json_encode($json, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        return true;
    }
}