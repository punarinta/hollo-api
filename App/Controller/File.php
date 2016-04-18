<?php

namespace App\Controller;

class File extends Generic
{
    /**
     * @return mixed
     * @throws \Exception
     */
    static public function findByEmail()
    {
        if (!$email = \Input::data('email'))
        {
            throw new \Exception('Email not provided.');
        }

        return \Sys::svc('File')->findByContact($email);
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
