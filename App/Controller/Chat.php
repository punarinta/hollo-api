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
        // issue an immediate sync
    //    \Sys::svc('Resque')->addJob('SyncContacts', ['user_id' => \Auth::user()->id]);

        $items = [];

        foreach (\Sys::svc('Chat')->findAllByUserId(\Auth::user()->id, \Input::data('filters') ?:[], \Input::data('sortBy'), \Input::data('sortMode')) as $chat)
        {
            // get flags
            if (!$flags = \Sys::svc('Chat')->getFlags($chat->id, \Auth::user()->id))
            {
                throw new \Exception('Chat is not set up. That should not be so.');
            }

            $lastMsg = null;

            if ($lastMsg = \Sys::svc('Message')->getLastByChatId($chat->id))
            {
                if ($lastMsg->body)
                {
                    $lastMsg = $lastMsg->body;
                }
                elseif ($lastMsg->files)
                {
                    $lastMsg = '📎';
                }
            }

            if (mb_strlen($lastMsg) > 25)
            {
                $lastMsg = mb_substr($lastMsg, 0, 25) . '…';
            }

            $items[] = array
            (
                'id'        => $chat->id,
                'name'      => $chat->name,
                'muted'     => $flags->muted,
                'read'      => $flags->read,
                'lastTs'    => $chat->last_ts,
                'lastMsg'   => $lastMsg,
                'users'     => \Sys::svc('User')->findByChatId($chat->id, true),
            );
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

        // prevent from setting an empty name
        if ($name = trim(\Input::data('name')))
        {
            $chat->name = $name;
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
