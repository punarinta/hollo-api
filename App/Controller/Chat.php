<?php

namespace App\Controller;

use MongoDB\BSON\ObjectID;
use \App\Service\User as UserSvc;
use \App\Service\Chat as ChatSvc;

/**
 * Class Chat
 * @package App\Controller
 * @doc-api-path /api/chat
 */
class Chat extends Generic
{
    /**
     * Literally, get all the data
     *
     * @doc-var     (int) lastTs         - Possible bottom time limit.
     * @doc-var     (string) chatId      - Limit to one chat only.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function getAllData()
    {
        $userIds = [];
        $myId = \Auth::user()->_id;

        if (!$chatId = \Input::data('chatId'))
        {
            // get all chats
            $chats = ChatSvc::findAll(['users' => ['$elemMatch' => ['id' => $myId]]]);
        }
        else
        {
            // get a specific chat, but check access
            if (!$chat = ChatSvc::findOne(['_id' => new ObjectID($chatId)]))
            {
                throw new \Exception('Chat not found.', 404);
            }

            if (!ChatSvc::hasAccess($chat, \Auth::user()->_id))
            {
                throw new \Exception('Access denied', 403);
            }

            $chats = [$chat];
        }

        // get all users
        foreach ($chats as $k => $chat)
        {
            foreach ($chat->users as $userItem)
            {
                if ($userItem->id != $myId && !isset ($userIds[$userItem->id]))
                {
                    $userIds[$userItem->id] = new ObjectID($userItem->id);
                }
            }

            foreach ($chat->messages ?? [] as $k2 => $message)
            {
                unset ($chat->messages[$k2]->refId);
                unset ($chat->messages[$k2]->extId);
            }
        }

        $users = UserSvc::findAll
        (
            ['_id' => ['$in' => array_values($userIds)]],
            ['projection' => ['_id' => 1, 'name' => 1, 'email' => 1]]
        );

        return array
        (
            'chats' => $chats,
            'users' => $users,
        );
    }

    /**
     * Adds a Chat with emails
     *
     * @doc-var     (array) emails         - List of emails. Yours is auto-included.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function add()
    {
        if (!$emails = \Input::data('emails'))
        {
            throw new \Exception('No emails provided.');
        }

        $emails[] = \Auth::user()->email;

        $chat = ChatSvc::init($emails);

        $chat->id = $chat->_id;
        unset ($chat->_id);

        return $chat;
    }

    /**
     * Updates Chat info
     *
     * @doc-var     (string) id!        - Chat ID.
     * @doc-var     (string) name       - Chat name.
     * @doc-var     (bool) muted        - Whether the Chat is muted for you or not.
     * @doc-var     (bool) read         - Whether the Chat is read by you or not.
     *
     * @throws \Exception
     */
    static public function update()
    {
        if (!$id = \Input::data('id'))
        {
            throw new \Exception('No ID provided.');
        }

        if (!$chat = ChatSvc::findOne(['_id' => new ObjectID($id)]))
        {
            throw new \Exception('Chat not found.', 404);
        }

        if (!ChatSvc::hasAccess($chat, \Auth::user()->_id))
        {
            throw new \Exception('Access denied', 403);
        }

        $doUpdate = false;

        if (($name = \Input::data('name')) !== null)
        {
            $chat->name = trim($name);
            $doUpdate = true;
        }

        if (($muted = \Input::data('muted')) !== null)
        {
            foreach ($chat->users as $k => $userItem)
            {
                if ($userItem->id == \Auth::user()->_id)
                {
                    $chat->users[$k]->muted = (int) $muted;
                    $doUpdate = true;
                }
            }
        }

        if (($read = \Input::data('read')) !== null)
        {
            foreach ($chat->users as $k => $userItem)
            {
                if ($userItem->id == \Auth::user()->_id)
                {
                    $chat->users[$k]->read = (int) $read;
                    $doUpdate = true;
                }
            }
        }

        if ($doUpdate)
        {
            ChatSvc::update($chat);
        }
    }

    /**
     * Leaves you from a chat
     *
     * @doc-var     (string) id!        - Chat ID.
     *
     * @throws \Exception
     */
    static public function leave()
    {
        if (!$id = \Input::data('id'))
        {
            throw new \Exception('No chat ID provided.');
        }

        return ChatSvc::dropUser($id, \Auth::user()->id);
    }
}
