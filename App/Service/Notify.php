<?php

namespace App\Service;

use App\Model\WebSocketClient;

class Notify extends Generic
{
    public $client = null;

    /**
     * A small abstraction to send data to WebSocket server
     *
     * @param $data
     */
    public function send($data)
    {
        if (!$this->client)
        {
            $cfg = \Sys::cfg('notifier');
            $this->client = new WebSocketClient;
            $this->client->connect($cfg['host'], $cfg['port'], '/', $cfg['origin']);
        }

        $this->client->sendData(json_encode($data));
    }
}
