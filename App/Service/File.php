<?php

namespace App\Service;

class File extends Generic
{
    /**
     * Returns files from Chat messages
     *
     * @param $chatId
     * @param bool $withImageUrl
     * @return array
     */
    public function findByChatId($chatId, $withImageUrl = false)
    {
        $items = [];

        foreach (\DB::rows('SELECT files FROM message WHERE chat_id=?', [$chatId]) as $row)
        {
            foreach (json_decode($row->files, true) ?: [] as $file)
            {
                $user = \Sys::svc('User')->findById(@$file['refId']);
                $userExtId = $user ? $user->ext_id : null;

                $url = ($withImageUrl && \Mime::isImage($file['type']) && $userExtId) ? $this->getProcessorLink($userExtId, $file['extId']) : null;

                $items[] = array
                (
                    'name'  => $file['name'],
                    'size'  => $file['size'],
                    'type'  => $file['type'],
                    'url'   => $url,
                    'refId' => $userExtId,
                    'extId' => @$file['extId'], // temporary files do not have extIds
                );
            }
        }

        return $items;
    }

    /**
     * Returns the contents of a file via processor
     *
     * @param $userExtId
     * @param $fileExtId
     * @return mixed
     */
    public function getProcessorContent($userExtId, $fileExtId)
    {
        return $this->conn->getFileContent($userExtId, ['file_id' => $fileExtId]);
    }

    /**
     * Returns the link to a file via processor
     *
     * @param $userExtId
     * @param $fileExtId
     * @return mixed
     */
    public function getProcessorLink($userExtId, $fileExtId)
    {
        return $this->conn->getFileContent($userExtId,
        [
            'file_id' => $fileExtId,
            'as_link' => 1,
        ]);
    }
}
