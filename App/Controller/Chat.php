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
     * @doc-var     (string) filter[].mode  - Filtering mode. Options are 'muted', 'name'.
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
            $flags = \Sys::svc('Chat')->getFlags($chat->id, \Auth::user()->id);

            if ($lastMsg = \Sys::svc('Message')->getLastByChatId($chat->id))
            {
                $lastMsg = $lastMsg->body;
            }
            else
            {
                $lastMsg = null;
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
                'count'     => $chat->count,
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
     * @return mixed
     * @throws \Exception
     */
    static public function add()
    {
        if (!$emails = \Input::data('emails'))
        {
            throw new \Exception('No emails provided.');
        }

        // check just in case
        if ($chat = \Sys::svc('Chat')->findByEmails($emails))
        {
            return $chat;
        }

        \DB::begin();

        try
        {
            // create chat itself
            $chat = \Sys::svc('Chat')->create(array
            (
                'name'      => null,
                'count'     => 0,
                'muted'     => 0,
                'read'      => 1,
                'last_ts'   => 0,
            ));

            $userIds = [];
            // assure that all the users exist
            foreach ($emails as $email)
            {
                if (!$user = \Sys::svc('User')->findByEmail($email))
                {
                    // create a dummy user
                    $user = \Sys::svc('User')->create(array
                    (
                        'email'     => $email,
                        'ext_id'    => null,
                        'roles'     => \Auth::USER,
                        'created'   => time(),
                        'settings'  => '',
                    ));
                }

                $userIds[] = $user->id;
            }

            // link users into the chat
            foreach ($userIds as $userId)
            {
                $stmt = \DB::prepare('INSERT INTO chat_user (`chat_id`, `user_id`) VALUES (?,?)', [$chat->id, $userId]);
                $stmt->execute();
                $stmt->close();
            }

            \DB::commit();
        }
        catch (\Exception $e)
        {
            \DB::rollback();
            throw $e;
        }

        return $chat;
    }

    /**
     * Updates Chat info
     *
     * @doc-var     (int) id!           - Chat ID.
     * @doc-var     (string) name       - Chat name.
     *
     * @throws \Exception
     */
    static public function update()
    {
        if (!$id = \Input::data('id'))
        {
            throw new \Exception('No ID provided.');
        }

        if (!$chat = \Sys::svc('Chat')->findByIdAndUserId($id, \Auth::user()->id))
        {
            throw new \Exception('Chat not found.');
        }

        // prevent from setting an empty name
        if ($name = trim(\Input::data('name')))
        {
            $chat->name = $name;
        }

        \Sys::svc('Chat')->update($chat);
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
