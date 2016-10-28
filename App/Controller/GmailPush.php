<?php

namespace App\Controller;

use App\Model\Inbox\Gmail;

class GmailPush
{
    /**
     * @return mixed
     * @throws \Exception
     */
    static public function index()
    {
        $json = json_decode(file_get_contents('php://input'), 1);

        self::log(date('Y-m-d H:i:s') . '|');

        if (isset ($json['message']['data']))
        {
            try
            {
                $x = json_decode(base64_decode($json['message']['data']), true);
                $user = \Sys::svc('User')->findByEmail($x['emailAddress']);

                if (!isset ($json['message']['message_id']))
                {
                    self::log("NO MSG-ID\n");
                    return false;
                }

                self::syncNew($user, $x['historyId']);

                self::log("|{$user->id}|{$x['historyId']}\n");
            }
            catch (\Exception $e)
            {
                self::log("ERR:{$e->getMessage()}\n");
                return false;
            }
        }
        else
        {
            self::log("NO MSG DATA\n");
            return false;
        }

        return true;
    }

    /**
     * @param $user
     * @param $historyId
     */
    static protected function syncNew($user, $historyId)
    {
        $inbox = new Gmail($user);

        foreach ($inbox->listHistory($historyId) as $row)
        {
            foreach ($row['messagesAdded'] as $data)
            {
                $messageData = $inbox->getMessage($data['message']['id']);

                $message = \Sys::svc('Message')->processMessageSync($user, $messageData);
                self::log("M{$message->id}");
            }
        }
    }

    /**
     * @param $text
     */
    static protected function log($text)
    {
        file_put_contents('data/files/pubsub.log', $text, FILE_APPEND);
    }
}
