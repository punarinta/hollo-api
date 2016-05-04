<?php

namespace App\Service;

class File extends Generic
{
    /**
     * Returns files associated with a contact
     *
     * @param $email
     * @param bool $withImageUrl
     * @return array
     */
    public function findByContact($email, $withImageUrl = false)
    {
        $items = [];

        // TODO: fetch from local DB (?)

        foreach ($this->conn->listContactFiles(\Auth::user()->ext_id, ['email' => $email, 'limit'=>10])->getData() as $row)
        {
            if ($withImageUrl && \Mime::isImage($row['type']))
            {
                $url = $this->getProcessorLink($row['file_id']);
            }
            else
            {
                $url = null;
            }

            $items[] = array
            (
                'messageId'     => $row['message_id'],
                'fileId'        => $row['file_id'],
                'name'          => $row['file_name_structure'][0][0],
                'ext'           => strtolower(@$row['file_name_structure'][1][0]),
                'size'          => $row['size'],
                'type'          => $row['type'],
                'url'           => $url,
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
