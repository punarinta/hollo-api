<?php

namespace App\Service;

class Message extends Generic
{
    /**
     * Returns messages associated with a contact
     *
     * @param $email
     * @return array
     */
    public function findByContact($email)
    {
        $items = [];

        foreach ($this->conn->listMessages(\Auth::user()->account_id, ['include_body' => 1, 'email' => $email])->getData() as $row)
        {
            $items[] = array
            (
                'ts'    => $row['date'],
                'body'  => $row['body'][0],
            );
        }

        return $items;
    }
}
