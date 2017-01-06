<?php

namespace App\Controller;

use MongoDB\BSON\ObjectID;
use \App\Service\Chat as ChatSvc;

/**
 * Class Track
 * @package App\Controller
 * @doc-api-path /api/track
 */
class Track extends Generic
{
    /**
     * Endpoint to receive tracking info
     *
     * @doc-var     (string) token!       - Tracking token
     *
     * @return \stdClass
     * @throws \Exception
     */
    static public function index()
    {
        if ((!$token = \Input::get('token')) || strlen($token) != 72)
        {
            throw new \Exception('Invalid tracking token.');
        }

        // token contains 72 symbols: 24 - chat ID, 24 - message ID, 24 - user ID
        $chatId = substr($token, 0, 24);
        $messageId = substr($token, 24, 24);
        $userId = substr($token, 48, 24);

        if (!$chat = ChatSvc::findOne(['_id' => new ObjectID($chatId)]))
        {
            throw new \Exception('Chat not found.', 404);
        }

        $found = false;

        foreach ($chat->messages ?? [] as $message)
        {
            if ($message->id == $messageId)
            {
                $found = true;
                break;
            }
        }

        if ($found)
        {
            $chatUsers = $chat->users ?? [];
            foreach ($chatUsers as $k => $user)
            {
                if ($user->id == $userId)
                {
                    $chatUsers[$k]->trk = time();
                    ChatSvc::update($chat, ['users' => $chatUsers]);
                    break;
                }
            }
        }

        // flush with a 1x1 transparent GIF

        header('Content-Type: image/gif');
        echo "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x90\x00\x00\xff\x00\x00\x00\x00\x00\x21\xf9\x04\x05\x10\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x04\x01\x00\x3b";

        return new \stdClass;
    }
}