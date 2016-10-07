<?php

namespace App\Controller;

/**
 * Class File
 * @package App\Controller
 * @doc-api-path /api/file
 */
class File extends Generic
{
   /**
     * Finds files related to this chat
     *
     * @doc-var     (int) chatId            - Chat ID.
     * @doc-var     (bool) withImageUrl     - Whether to extract image URL or not.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function findByChatId()
    {
        if (!$chatId = \Input::data('chatId'))
        {
            throw new \Exception('Chat ID not provided.');
        }

        if (!\Sys::svc('Chat')->hasAccess($chatId, \Auth::user()->id))
        {
            throw new \Exception('Access denied.', 403);
        }

        return \Sys::svc('File')->findByChatId($chatId, \Input::data('withImageUrl'));
    }

    /**
     * Gets a URL by external file ID and external user ID
     *
     * @doc-var     (int) extId     - External file ID.
     * @doc-var     (int) refId     - User reference ID.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function getFileUrl()
    {
        if (!$extFileId = \Input::data('extId'))
        {
            throw new \Exception('External ID not provided.');
        }

        if ($user = \Sys::svc('User')->findById(\Input::data('refId')))
        {
            return \Sys::svc('File')->getProcessorLink($user->ext_id, $extFileId);
        }

        return null;
    }
}
