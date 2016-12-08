<?php

namespace App\Model\CliTool;

use App\Model\Inbox\Inbox;
use \App\Service\Chat as ChatSvc;

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
     */
    public function file($messageId, $offset = 0)
    {
        if (!$chat = ChatSvc::findOne(['messages.id' => $messageId]))
        {
           return "Message not found\n";
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
        if (!$chat = ChatSvc::findOne(['messages.refId' => $userId, 'messages.extId' => $messageExtId]))
        {
            return "Message not found\n";
        }

        foreach ($chat->messages as $message)
        {
            if ($message->refId == $userId && $message->extId == $messageExtId)
            {
                $inbox = Inbox::init($userId);
                $data = $inbox->getMessage($messageExtId);

                return print_r($full ? $data : $data['body'][0]['content'], true);
            }
        }

        return "Error\n";
    }
}
