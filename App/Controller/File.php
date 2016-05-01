<?php

namespace App\Controller;

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
