<?php

namespace App\Model\CliTool;

use App\Model\ContextIO\ContextIO;
use App\Model\Inbox\Gmail;
use App\Model\Inbox\Imap;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\BulkWrite;

/**
 * Class Test
 * @package App\Model\CliTool
 */
class Test
{
    public function reClean()
    {
        $count = 0;

        foreach (\Sys::svc('Message')->findAll() as $message)
        {
            $count += 1 * \Sys::svc('Message')->reClean($message);
        }

        return "$count messages affected\n\n";
    }

    public function inbox($userId = 2)
    {
        $imap = new Imap($userId); // 11298 - attachment, 11216 - polish symbols

        print_r($imap->getMessages(['ts_after' => strtotime('2016-10-14')]));

    //    print_r($imap->getMessage(11077));

    //    $imap = new Gmail($userId);
    //    print_r($imap->getMessage('157c395527a42e95'));

        return '';
    }

    public function firebase()
    {
        $payload = array
        (
            'to'           => '/topics/user-1',
            'priority'     => 'high',

            'notification' => array
            (
                'title' => 'You have new message',          // Any value
                'body'  => 'Testing 🎂',                    // Any value
                'icon'  => 'fcm_push_icon'                  // White icon Android resource
            ),

            'data' => array
            (
                'authId' => 1,
                'cmd'    => 'show-chat',
                'chatId' => 1,
            ),
        );

        $res = \Sys::svc('Notify')->firebase($payload);

        print_r($res);
    }

    public function mongo()
    {
        $users = \Sys::svc('Chat')->findByEmails(['cheaterx@yandex.ru', 'hollo.email@gmail.com']);
        print_r($users);
    }

    public function mongo_populate()
    {
        $u = ["5838b014b641e39938288bd0", "58396830b641e39938288bd2", "5839d136b02c204c2d7e2c95"];

        $messages = [];
        $i = 50;
        while (--$i) $messages[] =
        [
            "userId" => $u[$i%3],
            "extId" => \Text::GUID_v4(),
            "refId" => $u[$i%3],
            "subj" => "test-" . mt_rand(1, 10),
            "body" => "some text " . mt_rand(1000, 9999),
            "ts" => 1337
        ];

        $i = 10000;
        while (--$i) $user = \Sys::svc('Chat')->create
        (
            [
                "name" => "the chat " . $i,
                "messages" => $messages,
                "users" => [
                    [
                        "id" => "5838b014b641e39938288bd0",
                        "muted" => 0,
                        "read" => 1
                    ],
                    [
                        "id" => "5839d136b02c204c2d7e2c95",
                        "muted" => 0,
                        "read" => 1
                    ]
                ]
            ]
        );
        //print_r($user);
    }
}
