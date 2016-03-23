<?php

namespace App\Service;
use App\Model\ContextIO\ContextIO;

class Communicator
{
    private $conn = null;

    public function __construct()
    {
        $cfg = \Sys::cfg('contextio');
        $this->conn = new ContextIO($cfg['key'], $cfg['secret']);
    }

    public function test()
    {
        $items = [];
        $accountId = '56f267aeb9d8cc767d8b456a';

        $r = $this->conn->listContacts($accountId, ['limit' => 20]);

        foreach ($r->getData() as $row)
        {
            $items[] = $row;
        }

        return $items;
    }
}
