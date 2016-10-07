<?php

namespace App\Model\CliTool;
use App\Model\ContextIO\ContextIO;

/**
 * Class Fetch
 * @package App\Model\CliTool
 */
class Fetch
{
    /**
     * @param $userId
     * @param $fileExtId
     * @return mixed|string
     */
    public function file($userId, $fileExtId)
    {
        $cfg = \Sys::cfg('contextio');
        $conn = new ContextIO($cfg['key'], $cfg['secret']);

        if (!$user = \Sys::svc('User')->findById($userId))
        {
            return "User not found: $userId\n";
        }

        return $conn->getFileContent($user->ext_id,
        [
            'file_id' => $fileExtId,
            'as_link' => 1,
        ]);
    }

    /**
     * @param $userId
     * @param $messageExtId
     * @param bool $full
     * @return mixed|string
     */
    public function message($userId, $messageExtId, $full = false)
    {
        $cfg = \Sys::cfg('contextio');
        $conn = new ContextIO($cfg['key'], $cfg['secret']);

        if (!$user = \Sys::svc('User')->findById($userId))
        {
            return "User not found: $userId\n";
        }

        if (!$data = $conn->getMessage($user->ext_id, ['message_id' => $messageExtId, 'include_body' => 1]))
        {
            return "Message not found: $messageExtId\n";
        }

        $data = $data->getData();

        if (!isset ($data['body']) && !$full)
        {
            return "No body? WTF?\n";
        }

        return print_r($full ? $data : $data['body'][0]['content'], true);
    }
}
