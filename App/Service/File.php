<?php

namespace App\Service;

class File extends Generic
{
    /**
     * Returns files associated with a contact
     *
     * @param $email
     * @param bool $withUrl
     * @return array
     */
    public function findByContact($email, $withUrl = false)
    {
        $items = [];

        // TODO: fetch from local DB (?)

        foreach ($this->conn->listContactFiles(\Auth::user()->ext_id, ['email' => $email, 'limit'=>10])->getData() as $row)
        {
            $items[] = array
            (
                'messageId'     => $row['message_id'],
                'fileId'        => $row['file_id'],
                'name'          => $row['file_name_structure'][0][0],
                'ext'           => strtolower($row['file_name_structure'][1][0]),
                'size'          => $row['size'],
                'type'          => $row['type'],
                'url'           => $withUrl ? $this->getProcessorLink($row['file_id']) : null,
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
        return $this->conn->getFileContent(\Auth::user()->ext_id, ['file_id' => $procFileId]);
    }

    /**
     * Returns the link to a file via processor
     *
     * @param $procFileId
     * @return mixed
     * @throws \Exception
     */
    public function getProcessorLink($procFileId)
    {
        return $this->conn->getFileContent(\Auth::user()->ext_id,
        [
            'file_id' => $procFileId,
            'as_link' => 1,
        ]);
    }
}
