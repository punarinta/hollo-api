<?php

namespace App\Service;

use App\Model\WebSocketClient;

class Notify extends Generic
{
    public $client = null;

    /**
     * Send data to WebSocket server
     *
     * @param $data
     */
    public function im($data)
    {
        if (!$this->client)
        {
            $cfg = \Sys::cfg('notifier');
            $this->client = new WebSocketClient;
            $this->client->connect($cfg['host'], $cfg['port'], '/', $cfg['origin'], $cfg['ssl']);
        }

        $this->client->sendData(json_encode($data));
    }

    /**
     * Send data to Firebase server
     *
     * @param $data
     * @return bool
     */
    public function firebase($data)
    {
        $data['notification']['sound'] = 'default';
        $data['notification']['click_action'] = 'FCM_PLUGIN_ACTIVITY';

        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type:application/json", "Authorization:key=" . \Sys::cfg('notifier.firebase')));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $output = curl_exec($ch);

        if ($output === false && @$GLOBALS['-SYS-VERBOSE'])
        {
            echo 'Curl error: ' . curl_error($ch) . "\n";
        }

        $res = json_decode($output, true) ?:[];
        curl_close($ch);

        if (!isset ($res['message_id']) && @$GLOBALS['-SYS-VERBOSE'])
        {
            echo "Wrong return structure:\n";
            print_r($res);
        }

        return @$res['message_id'] ?: false;
    }
}
