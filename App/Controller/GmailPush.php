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

        if (isset ($json['message']['data']))
        {
            $json['message']['x'] = base64_decode($json['message']['data']);
        }

        file_put_contents('data/files/pubsub.log', json_encode($json), FILE_APPEND);

        return true;
    }
}