<?php

namespace App\Model\CliTool;
use App\Model\Inbox\Inbox;

/**
 * Class Fetch
 * @package App\Model\CliTool
 */
class Fetch
{
    /**
     * @param $messageId
     * @param $offset
     * @return string
     * @throws \Exception
     */
    public function file($messageId, $offset = 0)
    {
        if (!$chat = \Sys::svc('Chat')->findOne(['messages.id' => $messageId]))
        {
            throw new \Exception('Message not found');
        }

        foreach ($chat->messages as $message)
        {
            if ($message->id == $messageId)
            {
                $fileName = 'data/temp/fetched';
                $inbox = Inbox::init($message->refId);

                file_put_contents($fileName, $inbox->getFileData($message->extId, $offset));

                return "File saved to '$fileName'.\n";
            }
        }

        return "Error\n";
    }

    /**
     * @param $userId
     * @param $messageExtId
     * @param bool $full
     * @return mixed|string
     */
    public function message($userId, $messageExtId, $full = false)
    {
        if (!$user = \Sys::svc('User')->findById($userId))
        {
            return "User not found: $userId\n";
        }

        $inbox = Inbox::init($user);
        $data = $inbox->getMessage($messageExtId);

        return print_r($full ? $data : $data['body'][0]['content'], true);
    }
}
