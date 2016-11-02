<?php

namespace App\Controller;

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
        $myId = \Auth::user()->id;
        $filters = \Input::data('filters') ?:[];

        foreach (\Sys::svc('Chat')->findAllByUserId($myId, $filters, \Input::data('sortBy'), \Input::data('sortMode')) as $chat)
        {
            \DB::$pageStart = null;

            $lastMsgBody = null;
            $lastMsgSubj = null;

            if ($lastMsg = \Sys::svc('Message')->getLastByChatId($chat->id))
            {
                // TODO: move the logic below onto frontend
                if ($lastMsg->body)
                {
                    $lastMsgBody = $lastMsg->body;
                }
                elseif ($lastMsg->files)
                {
                    $lastMsgBody = '📎';
                }
                else
                {
                    $lastMsgBody = '';
                }

                $lastMsgSubj = $lastMsg->subject;
            }

            $items[] = array
            (
                'id'        => $chat->id,
                'name'      => $chat->name,
                'muted'     => $chat->muted,
                'read'      => $chat->read,
                'last'  => array
                (
                    'ts'    => $chat->last_ts,
                    'msg'   => $lastMsgBody,
                    'subj'  => $lastMsgSubj,
                ),
                'users'     => \Sys::svc('User')->findByChatId($chat->id, true, $myId),
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
     * @doc-var     (array) emails         - List of emails. Yours is included.
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

        $userId = \Auth::user()->id;

        if (!$chat = \Sys::svc('Chat')->findByIdAndUserId($id, $userId))
        {
            throw new \Exception('Chat not found.');
        }

        if (($name = \Input::data('name')) !== null)
        {
            $chat->name = trim($name);
            \Sys::svc('Chat')->update($chat);
        }

        // a bit more logic here may save some DB requests in the future
        if (\Input::data('muted') !== null && \Input::data('read') !== null)
        {
            $flags = new \stdClass();
            $flags->read = \Input::data('read');
            $flags->muted = \Input::data('muted');
            \Sys::svc('Chat')->setFlags($id, $userId, $flags);
        }
        else
        {
            if (\Input::data('muted') !== null)
            {
                \Sys::svc('Chat')->setMutedFlag($id, $userId, \Input::data('muted'));
            }

            if (\Input::data('read') !== null)
            {
                \Sys::svc('Chat')->setReadFlag($id, $userId, \Input::data('read'));
            }
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

        if (!$chat = \Sys::svc('Chat')->findByIdAndUserId($id, \Auth::user()->id))
        {
            throw new \Exception('Chat not found.');
        }

        return \Sys::svc('Chat')->dropUser($chat->id, \Auth::user()->id);
    }
}
