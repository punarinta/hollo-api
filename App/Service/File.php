<?php

namespace App\Service;

class File extends Generic
{
    /**
     * Returns files associated with a contact
     *
     * @param $email
     * @return array
     */
    public function findByContact($email)
    {
        $items = [];

        foreach ($this->conn->listContactFiles(\Auth::user()->account_id, ['email' => $email])->getData() as $row)
        {
            $items[] = array
            (
                'messageId'     => $row['message_id'],
                'fileId'        => $row['file_id'],
                'name'          => $row['file_name_structure'][0][0],
                'ext'           => strtolower($row['file_name_structure'][1][0]),
                'size'          => $row['size'],
                'type'          => $row['type'],
            );
        }

        return $items;
    }
}
