<?php

namespace App\Controller;

class GmailPush
{
    /**
     * @return mixed
     * @throws \Exception
     */
    static public function index()
    {
        $json = json_decode(file_get_contents('php://input'), 1);

        file_put_contents('data/files/pubsub.log', date('Y-m-d H:i:s') . '|', FILE_APPEND);

        if (isset ($json['message']['data']))
        {
            try
            {
                $x = json_decode(base64_decode($json['message']['data']), true);
                $user = \Sys::svc('User')->findByEmail($x['emailAddress']);

                if (!isset ($json['message']['message_id']))
                {
                    file_put_contents('data/files/pubsub.log', "NO MSG-ID\n", FILE_APPEND);
                    return false;
                }

                $messageId = $json['message']['message_id'];

                \Sys::svc('Message')->sync($user->id, $messageId, false);

                file_put_contents('data/files/pubsub.log', "{$user->id}|{$messageId}\n", FILE_APPEND);
            }
            catch (\Exception $e)
            {
                file_put_contents('data/files/pubsub.log', "ERR:{$e->getMessage()}\n", FILE_APPEND);
                return false;
            }
        }
        else
        {
            file_put_contents('data/files/pubsub.log', "NO MSG DATA\n", FILE_APPEND);
            return false;
        }

        return true;
    }
}
