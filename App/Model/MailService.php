<?php

namespace App\Model;

/**
 * Class MailService
 * @package App\Model
 */
class MailService
{
    static public function getService($id)
    {
        if (!file_exists($fileName = 'mail-services/' . $id . '.json'))
        {
            throw new \Exception('Unsupported mail service');
        }

        return json_decode(file_get_contents($fileName), true);
    }
}