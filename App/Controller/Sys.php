<?php

namespace App\Controller;

use App\Service\Contact as ContactSvc;
use \App\Service\User as UserSvc;
use \App\Service\MailService as MailServiceSvc;
use MongoDB\BSON\Regex;

/**
 * Class Sys
 * @package App\Sys
 * @doc-api-path /api/sys
 */
class Sys extends Generic
{
    /**
     * @return array
     */
    static public function stats()
    {
        $result = [];
        $gmailCount = 0;
        $gmailId = MailServiceSvc::findOne(['name' => 'Gmail'])->_id;

        // get real users
        $users = UserSvc::findAll(['roles' => ['$gt' => 0]]);

        $result['users']['total'] = count($users);

        foreach ($users as $user)
        {
            if ($user->settings->svc == $gmailId) ++$gmailCount;
        }

        $result['users']['gmail'] = $gmailCount;

        $result['count'] =
        [
            'emails'        => \DB::command(['collStats' => 'user'])->toArray()[0]->count,
            'chats'         => \DB::command(['collStats' => 'chat'])->toArray()[0]->count,
            'preMuted'      => \DB::command(['collStats' => 'muted'])->toArray()[0]->count,
            'mailServices'  => \DB::command(['collStats' => 'mail_service'])->toArray()[0]->count,
        ];

        return $result;
    }

    static public function discover()
    {
        if ((!$token = \Input::data('token')) || strlen($token) < 3)
        {
            throw new \Exception('No token specified or it is too short');
        }

        $items = [];

        // case-insensitive token search
        foreach (UserSvc::findAll(['name' => ['$regex' => new Regex($token, 'i')]]) as $user)
        {
            if (ContactSvc::isMuted($user->email))
            {
                continue;
            }

            $items[] = array
            (
                'email' => $user->email,
                'name'  => @$user->name,
            );
        }

        return $items;
    }

    static public function throwError()
    {
        throw new \Exception('Test error');
    }
}
