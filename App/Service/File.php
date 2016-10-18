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

        // TODO: add some kind of lazy loading
        foreach (\DB::rows("SELECT id, files, ref_id FROM message WHERE chat_id=? AND files!='' AND ref_id IS NOT NULL ORDER BY ts DESC LIMIT 10", [$chatId]) as $row)
        {
            foreach (json_decode($row->files, true) ?: [] as $file)
            {
                $user = \Sys::svc('User')->findById($row->ref_id);

            //    $url = ($withImageUrl && \Mime::isImage($file['type']) && $user->roles && $count < 10) ? $this->getProcessorLink($userExtId, $file['extId']) : null;

                $items[] = array
                (
                    'name'  => $file['name'],
                    'size'  => $file['size'],
                    'type'  => $file['type'],
                    'url'   => null,
                    'refId' => $row->ref_id,
                    'msgId' => $row->id,
                );
            }
        }

        return $items;
    }
}
