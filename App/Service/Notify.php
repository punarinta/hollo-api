<?php

namespace App\Service;

use App\Model\WebSocketClient;

class Notify
{
    public static $client = null;

    /**
     * @param $userIds
     * @param $data
     * @param null $push
     * @return array
     */
    public static function auto($userIds, $data, $push = null)
    {
        $uid = uniqid('', true);
        $returns = ['firebase' => []];

        $imData =
        [
            'userIds'   => $userIds,
            'uid'       => $uid,
        ];

        $imData = array_merge($imData, $data);
        $returns['im'] = Notify::im($imData);

        foreach ($userIds as $userId)
        {
            $firebaseData =
            [
                'authId' => $userId,
                'uid'    => $uid,
            ];

            $firebaseData = array_merge($firebaseData, $data);

            $firebasePayload =
            [
                'to'            => '/topics/user-' . $userId,
                'collapse_key'  => 'new_message',   // TODO: elaborate on this
                'priority'      => 'high',
                'data'          => $firebaseData,
            ];

            if ($push)
            {
                $firebasePayload['notification'] = array_merge(['icon'  => 'fcm_push_icon'], $push);
            }

            $returns['firebase'][] = Notify::firebase($firebasePayload, (bool) $push);
        }

        return $returns;
    }

    /**
     * Send data to WebSocket server
     *
     * @param $data
     * @return bool
     */
    public static function im($data)
    {
        if (!self::$client)
        {
            $cfg = \Sys::cfg('notifier');
            self::$client = new WebSocketClient;
            self::$client->connect($cfg['host'], $cfg['port'], '/', $cfg['origin'], $cfg['ssl']);
        }

        $data['key'] = 'S5tDDFnqq2Xn$Wh%ES0H!Y58W)"F8g$AvI"DJ$EmZRfO5PVesNbGgWSK$k(5E#VC';

        return self::$client->sendData(json_encode($data));
    }

    /**
     * Send data to Firebase server
     *
     * @param $data
     * @param bool $withBg
     * @return bool
     */
    public static function firebase($data, $withBg = true)
    {
        if ($withBg)
        {
            $data['notification']['sound'] = 'default';
            $data['notification']['click_action'] = 'FCM_PLUGIN_ACTIVITY';

            if (isset ($data['notification']['body']))
            {
                $messageBody = trim($data['notification']['body']);

                // make message notifiable
                // TODO: allow other types in advance
                if (mb_strpos($messageBody, '{"widget":') !== false)
                {
                    $messageBody = 'Tap to see calendar';
                }

                $messageBody = str_replace('[sys:fwd]', ' Forwarded message', $messageBody);

                $data['notification']['body'] = $messageBody;
            }
        }
        else
        {
            unset ($data['notification']);
        }

        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type:application/json", "Authorization:key=" . \Sys::cfg('notifier.firebase')));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $output = curl_exec($ch);

        if ($output === false && @$GLOBALS['-SYS-VERBOSE'])
        {
            echo 'Curl error: ' . curl_error($ch) . "\n";
        }

        $res = json_decode($output, true) ?:[];
        curl_close($ch);

        if (!isset ($res['message_id']) && @$GLOBALS['-SYS-VERBOSE'])
        {
            echo "Wrong return structure:\n";
            print_r($res);
        }

        return @$res['message_id'] ?: false;
    }
}
