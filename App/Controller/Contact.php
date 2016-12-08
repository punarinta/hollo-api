<?php

namespace App\Controller;

use MongoDB\BSON\ObjectID;
use \App\Service\Chat as ChatSvc;
use \App\Service\User as UserSvc;

/**
 * Class Contact
 * @package App\Controller
 * @doc-api-path /api/contact
 */
class Contact extends Generic
{
    /**
     * Lists all your contacts
     *
     * @return array
     */
    static public function find()
    {
        $users = [];
        $userIds = [];
        $emailFilter = null;

        foreach (\Input::data('filters') ?? [] as $filter)
        {
            if ($filter['mode'] == 'email')
            {
                $emailFilter = $filter['value'];
                break;
            }
        }

        $chats = ChatSvc::findAllByUserId(\Auth::user()->_id);

        foreach ($chats as $chat) foreach ($chat->users as $userRow)
        {
            // save ObjectIDs
            if (isset ($userIds[$userRow->id])) continue;
            $userIds[$userRow->id] = new ObjectID($userRow->id);
        }

        foreach (UserSvc::findAll(['_id' => ['$in' => array_values($userIds)]], ['projection' => ['_id' => 1, 'name' => 1, 'email' => 1]]) as $user)
        {
            if ($emailFilter && stripos($user->email, $emailFilter) === false)
            {
                continue;
            }

            $users[] = array
            (
                'id'    => $user->_id,
                'email' => $user->email,
                'name'  => @$user->name,
            );
        }

        return $users;
    }
}
