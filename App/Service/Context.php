<?php

namespace App\Service;
use App\Model\ContextIO\ContextIO;

class Context
{
    public $conn = null;
    public $accountId = null;

    public function __construct()
    {
        $this->accountId = '56f267aeb9d8cc767d8b456a';
        $cfg = \Sys::cfg('contextio');
        $this->conn = new ContextIO($cfg['key'], $cfg['secret']);
    }

    public function listContacts()
    {
        $items = [];

        foreach ($this->conn->listContacts($this->accountId, ['limit' => 50])->getData() as $row)
        {
            $items[] = $row;
        }

        return $items[1];
    }

    public function listContactMessages($email)
    {
        $items = [];

        foreach ($this->conn->listMessages($this->accountId, ['include_body' => 1, 'email' => $email])->getData() as $row)
        {
            $items[] = array
            (
                'ts'    => $row['date'],
                'body'  => $row['body'][0],
            );
        }

        return $items;
    }

    public function listContactFiles($email)
    {
        $items = [];

        foreach ($this->conn->listContactFiles($this->accountId, ['email' => $email])->getData() as $row)
        {
            $items[] = array
            (
                'messageId'     => $row['message_id'],
                'fileId'        => $row['file_id'],
                'name'          => $row['file_name_structure'][0][0],
                'ext'           => $row['file_name_structure'][1][0],
                'size'          => $row['size'],
                'type'          => $row['type'],
            );
        }

        return $items;
    }
}
