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
     * Finds contact's files
     *
     * @doc-var     (string) email          - Contact email.
     * @doc-var     (bool) withImageUrl     - Whether to extract image URL or not.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function findByEmail()
    {
        if (!$email = \Input::data('email'))
        {
            throw new \Exception('Email not provided.');
        }

        return \Sys::svc('File')->findByContact($email, \Input::data('withImageUrl'));
    }

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

        $files = [];
        $flag = \Input::data('withImageUrl');

        foreach (\Sys::svc('Chat')->findByChatId($chatId) as $user)
        {
            $files = array_merge($files, \Sys::svc('File')->findByContact($user->email, $flag));
        }

        return $files;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    static public function getFileUrl()
    {
        if (!$extId = \Input::data('extId'))
        {
            throw new \Exception('External ID not provided.');
        }
        
        return \Sys::svc('File')->getProcessorLink($extId);
    }
}
