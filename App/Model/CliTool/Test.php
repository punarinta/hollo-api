<?php

namespace App\Model\CliTool;

use App\Model\Inbox\Imap;
use \App\Service\Chat as ChatSvc;
use \App\Service\Notify as NotifySvc;

/**
 * Class Test
 * @package App\Model\CliTool
 */
class Test
{
    public function reClean()
    {
        $count = 0;

        // TODO: refactor based on chats
      /*  foreach (\Sys::svc('Message')->findAll() as $message)
        {
            $count += 1 * \Sys::svc('Message')->reClean($message);
        }*/

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

        $res = NotifySvc::firebase($payload);

        print_r($res);
    }

    public function mongo()
    {
        $users = ChatSvc::findByEmails(['felix.r.lange@gmail.com', 'fredrik.engblom@gmail.com']);
        print_r($users);
    }
}
