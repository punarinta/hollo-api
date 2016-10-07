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
        $count = 0;

        // TODO: add some kind of lazy loading
        foreach (\DB::rows("SELECT files, ref_id FROM message WHERE chat_id=? AND files!='' AND ref_id IS NOT NULL ORDER BY ts DESC LIMIT 10", [$chatId]) as $row)
        {
            foreach (json_decode($row->files, true) ?: [] as $file)
            {
                $user = \Sys::svc('User')->findById($row->ref_id);
                $userExtId = $user ? $user->ext_id : null;

                if ($url = ($withImageUrl && \Mime::isImage($file['type']) && $userExtId && $count < 10) ? $this->getProcessorLink($userExtId, $file['extId']) : null)
                {
                    ++$count;
                }

                $items[] = array
                (
                    'name'  => $file['name'],
                    'size'  => $file['size'],
                    'type'  => $file['type'],
                    'url'   => $url,
                    'refId' => $row->ref_id,
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
