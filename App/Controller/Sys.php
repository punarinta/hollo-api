<?php

namespace App\Controller;

use MongoDB\BSON\ObjectID;

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
        $gmailId = \Sys::svc('MailService')->findOne(['name' => 'Gmail'])->_id;

        // get real users
        $users = \Sys::svc('User')->findAll(['roles' => ['$gt' => 0]]);

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
}
