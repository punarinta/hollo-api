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

        foreach ($this->conn->listContactFiles(\Auth::user()->context_id, ['email' => $email])->getData() as $row)
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

    /**
     * Returns the contents of a file via processor
     *
     * @param $procFileId
     * @return mixed
     * @throws \Exception
     */
    public function getProcessorContent($procFileId)
    {
        return $this->conn->getFileContent(\Auth::user()->context_id, ['file_id' => $procFileId]);
    }
}
