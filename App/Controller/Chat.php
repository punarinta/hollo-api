<?php

namespace App\Controller;
use MongoDB\BSON\ObjectID;

/**
 * Class Chat
 * @package App\Controller
 * @doc-api-path /api/chat
 */
class Chat extends Generic
{
    /**
     * Lists your chats
     *
     * @doc-var     (string) sortBy         - Sorting key. Options are 'name', 'lastTs'.
     * @doc-var     (string) sortMode       - Sorting mode. Options are 'asc', 'desc'.
     * @doc-var     (array) filters         - Array of 'filter'.
     * @doc-var     (string) filter[].mode  - Filtering mode. Options are 'muted', 'name', 'email'.
     * @doc-var     (string) filter[].value - Filter string.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function find()
    {
        $items = [];
        $myId = \Auth::user()->_id;
        $filters = \Input::data('filters') ?:[];

        foreach (\Sys::svc('Chat')->findAllByUserId($myId, $filters, \Input::data('sortBy'), \Input::data('sortMode')) as $chat)
        {
            $lastMsg = null;
            $lastMsgBody = null;
            $msgCount = count($chat->messages);

            if ($msgCount)
            {
                $lastMsg = $chat->messages[$msgCount - 1];

                // TODO: move the logic below onto frontend
                if ($lastMsg->body)
                {
                    $lastMsgBody = $lastMsg->body;
                }
                elseif ($lastMsg->files)
                {
                    $lastMsgBody = '📎 ' . $lastMsg->files[0]->name;
                }
                else
                {
                    $lastMsgBody = '';
                }
            }

            $read = 1;
            $muted = 0;
            $scanIds = [];

            foreach ($chat->users as $userItem)
            {
                if ($userItem->id == $myId)
                {
                    $read = $userItem->read;
                    $muted = $userItem->muted;
                }
                else
                {
                    $scanIds[] = new ObjectID($userItem->id);
                }
            }

            // no pagination
            \DB::$pageLength = 0;

            $users = [];
            foreach (\Sys::svc('User')->findAll(['_id' => ['$in' => $scanIds]], ['projection' => ['_id' => 1, 'name' => 1, 'email' => 1]]) as $user)
            {
                $users[] = array
                (
                    'id'    => $user->_id,
                    'name'  => $user->name,
                    'email' => $user->email,
                );
            }

            $items[] = array
            (
                'id'        => $chat->_id,
                'name'      => @$chat->name,
                'muted'     => $muted,
                'read'      => $read,
                'last'      => $msgCount ?
                [
                    'ts'    => $lastMsg->ts,
                    'msg'   => $lastMsgBody,
                    'subj'  => $lastMsg->subj,
                ] : [],
                'users'     => $users,
            );

            foreach ($filters as $filter)
            {
                if ($filter['mode'] == 'email')
                {
                    usort($items, function ($a, $b)
                    {
                        return count($a['users']) > count($b['users']);
                    });
                }
            }
        }

        return $items;
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

        return \Sys::svc('Chat')->init($emails);
    }

    /**
     * Updates Chat info
     *
     * @doc-var     (int) id!           - Chat ID.
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

        if (!$chat = \Sys::svc('Chat')->findOne(['_id' => new ObjectID($id)]))
        {
            throw new \Exception('Chat not found.');
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
                    $chat->users[$k]->muted = $muted;
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
                    $chat->users[$k]->read = $read;
                    $doUpdate = true;
                }
            }
        }

        if ($doUpdate)
        {
            \Sys::svc('Chat')->update($chat);
        }
    }

    /**
     * Leaves you from a chat
     *
     * @doc-var     (int) id!       - Chat ID.
     *
     * @throws \Exception
     */
    static public function leave()
    {
        if (!$id = \Input::data('id'))
        {
            throw new \Exception('No chat ID provided.');
        }

        return \Sys::svc('Chat')->dropUser($id, \Auth::user()->id);
    }
}
