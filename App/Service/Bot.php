<?php

namespace App\Service;

class Bot extends Generic
{
    /**
     * Talk to a bot
     *
     * @param $bot      -- bot user
     * @param $chatId
     * @param $text
     * @return null
     */
    public function talk($bot, $chatId, $text)
    {
        $message = null;
        $botName = str_replace('@bot.hollo.email', '', $bot->email);

        if ($botName == 'ping')
        {
            $message = \Sys::svc('Message')->create(array
            (
                'ext_id'    => '',
                'user_id'   => $bot->id,
                'chat_id'   => $chatId,
                'subject'   => 'Bot response',
                'body'      => "Hello from '$botName'! You said: '$text'.",
                'files'     => '',
                'ts'        => time(),
            ));

            $userIds = [];

            foreach (\Sys::svc('User')->findByChatId($chatId, true, $bot->id) as $user)
            {
                // collect user IDs for IM notification
                $userIds[] = $user->id;
            }

            \Sys::svc('Notify')->im(['cmd' => 'notify', 'userIds' => $userIds, 'chatId' => $chatId]);
        }

        return $message;
    }
}
