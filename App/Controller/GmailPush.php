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

    //    self::log("\n--- " . json_encode($json) . " ---:\n");

        self::log("\n" . date('d/m H:i:s') . '|');

        if (isset ($json['message']['data']))
        {
            $x = json_decode(base64_decode($json['message']['data']), true);
            $user = \Sys::svc('User')->findOne(['email' => $x['emailAddress']]);

            self::log("{$user->email}|H={$x['historyId']}|");
            self::syncNew($user, $x['historyId']);
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
     * @param $newHistoryId
     */
    static protected function syncNew($user, $newHistoryId)
    {
        $inbox = new Gmail($user);

        foreach ($inbox->listHistory($newHistoryId, null) as $row)
        {
            // history may be empty at all

            if (isset ($row['messagesAdded'])) foreach ($row['messagesAdded'] as $data)
            {
                if (in_array('CHAT', $data['message']['labelIds']))
                {
                    // save resources
                    continue;
                }

                self::log("E={$data['message']['id']}");

                try
                {
                    $message = \Sys::svc('Message')->sync($user, $data['message']['id']);
                    self::log($message ? " M={$message->id}," : ' [*],');
                }
                catch (\Exception $e)
                {
                    self::log(print_r($e->getMessage(), true));
                }
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
