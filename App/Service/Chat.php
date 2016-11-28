<?php

namespace App\Service;

use MongoDB\BSON\ObjectID;

class Chat extends Generic
{
    /**
     * Initializes a new Chat with a specific set of participants
     *
     * @param array $emails
     * @param array $names
     * @return mixed|null
     */
    public function init($emails = [], $names = [])
    {
        $emails = array_unique($emails);

        // check just in case
        if ($chat = $this->findByEmails($emails))
        {
            return $chat;
        }

        $muteThis = false;
        $chatUsers = [];

        foreach ($emails as $email)
        {
            if (\Sys::svc('Contact')->isMuted($email))
            {
                $muteThis = true;
            }
        }

        // create users first
        foreach ($emails as $email)
        {
            if (!$user = \Sys::svc('User')->findOne(['email' => $email]))
            {
                $userStructure = ['email' => $email];
                if (isset ($names[$email]) && $names[$email] != $email)
                {
                    $userStructure['name'] = $names[$email];
                }

                $user = \Sys::svc('User')->create($userStructure);
            }

            $chatUsers[] = (object)
            [
                'id'    => $user->_id,
                'read'  => 1,
                'muted' => $muteThis,
            ];
        }

        return $this->create(['users' => $chatUsers]);
    }

    /**
     * Returns chats associated with the current user account
     *
     * @param $userId
     * @param array $filters
     * @param null $sortBy
     * @param null $sortMode
     * @return array
     */
    public function findAllByUserId($userId, $filters = [], $sortBy = null, $sortMode = null)
    {
        return \Sys::svc('Chat')->findAll
        (
            ['users.id' => $userId],
            [
                'projection' => ['messages' => ['$slice' => -1]],   // get last message only
                'sort' => ['lastTs' => -1],
            ]
        );

    /*    $sql = 'SELECT c.id, c.name, c.last_ts, cu.`read`, cu.muted
                FROM chat AS c LEFT JOIN chat_user AS cu ON cu.chat_id=c.id WHERE cu.user_id=?';

        // TODO: rewrite in a proper way
        if (@$filters[0]['mode'] == 'email' || @$filters[1]['mode'] == 'email')
        {
            $sql = 'SELECT DISTINCT c.id, c.name, c.last_ts, cu.read, cu.muted FROM chat AS c
                    LEFT JOIN chat_user AS cu ON cu.chat_id = c.id
                    LEFT JOIN chat_user AS cu2 ON cu2.chat_id = c.id
                    LEFT JOIN `user` AS u ON cu2.user_id = u.id
                    WHERE cu.user_id=? AND cu2.user_id != cu.user_id';
        }

        $params = [$userId];

        foreach ($filters as $filter)
        {
            switch ($filter['mode'])
            {
                case 'name':
                    $sql .= ' AND `name` LIKE ?';
                    break;

                case 'muted':
                    $sql .= ' AND cu.muted=?';
                    break;

                case 'read':
                    $sql .= ' AND cu.read=?';
                    break;

                case 'email':
                    $sql .= ' AND u.email LIKE ?';
                    $filter['value'] = '%' . $filter['value'] . '%';
                    break;
            }

            $params[] = $filter['value'];
        }

        if ($sortBy)
        {
            if ($sortBy == 'name')
            {
                $sql .= ' ORDER BY cu.read ASC, `name`';
            }
            elseif ($sortBy == 'lastTs')
            {
                $sql .= ' ORDER BY cu.read ASC, last_ts DESC';
            }

            if ($sortMode == 'desc')
            {
                $sql .= ' DESC';
            }
        }
        else
        {
            $sql .= ' ORDER BY `read` ASC, `name` ASC';
        }

        return \DB::rows($sql, $params);*/
    }

    /**
     * Gets 'read' and 'muted' flags for given Chat and User
     *
     * @param $chat
     * @param $userId
     * @return null
     */
    public function getFlags($chat, $userId)
    {
        if (!$userId)
        {
            return null;
        }

        if (!is_object($chat))
        {
            $chat = $this->findOne(['_id' => $chat], ['projection' => ['users' => 1]]);
        }

        foreach ($chat->users ?? [] as $userItem)
        {
            if ($userItem->id == $userId)
            {
                $flags = new \stdClass;
                $flags->read = $userItem->read;
                $flags->muted = $userItem->muted;
                return $flags;
            }
        }

        return null;
    }

    /**
     * Sets 'read' or/and 'muted' flags for given Chat and User
     *
     * @param $chat
     * @param $userId
     * @param $flags
     * @return bool
     */
    public function setFlags($chat, $userId, $flags)
    {
        if (!is_object($chat))
        {
            $chat = $this->findOne(['_id' => $chat], ['projection' => ['users' => 1]]);
        }

        $chatUsers = $chat->users;

        foreach ($chatUsers ?? [] as $k => $userRow)
        {
            if ($userRow->id == $userId)
            {
                $chatUsers[$k]->read = $flags->read;
                $chatUsers[$k]->muted = $flags->muted;
                \Sys::svc('Chat')->update($chat, ['users' => $chatUsers]);

                return true;
            }
        }

        return false;
    }

    /**
     * Sets 'muted' flag for given Chat and User
     *
     * @param $chat
     * @param $userId
     * @param $muted
     * @return bool
     */
    public function setMutedFlag($chat, $userId, $muted)
    {
        if (!is_object($chat))
        {
            $chat = $this->findOne(['_id' => $chat], ['projection' => ['users' => 1]]);
        }

        $chatUsers = $chat->users;

        foreach ($chatUsers ?? [] as $k => $userRow)
        {
            if ($userRow->id == $userId)
            {
                $chatUsers[$k]->muted = $muted;
                \Sys::svc('Chat')->update($chat, ['users' => $chatUsers]);

                return true;
            }
        }

        return false;
    }

    /**
     * Sets 'read' flag for given Chat and User
     *
     * @param $chat
     * @param $userId
     * @param $read
     */
    public function setReadFlag($chat, $userId, $read)
    {
        if (!is_object($chat))
        {
            $chat = $this->findOne(['_id' => $chat], ['projection' => ['users' => 1]]);
        }

        $chatUsers = $chat->users;

        foreach ($chatUsers ?? [] as $k => $userRow)
        {
            if ($userId > 0)
            {
                if ($userRow->id == $userId)
                {
                    $chatUsers[$k]->read = $read;
                }
            }
            else if ($userId < 0)
            {
                if ($userRow->id != $userId)
                {
                    $chatUsers[$k]->read = $read;
                }
            }
            else
            {
                $chatUsers[$k]->read = $read;
            }
        }

        \Sys::svc('Chat')->update($chat, ['users' => $chatUsers]);
    }

    /**
     * Counts Users in the Chat
     *
     * @param $chat
     * @return mixed
     */
    public function countUsers($chat)
    {
        if (!is_object($chat))
        {
            $chat = $this->findOne(['_id' => $chat], ['projection' => ['users' => 1]]);
        }

        return count($chat->users ?? []);
    }

    /**
     * Check is User has access to Chat
     *
     * @param $chat
     * @param $userId
     * @return bool
     */
    public function hasAccess($chat, $userId)
    {
        foreach ($chat->users ?? [] as $userItem)
        {
            if ($userItem->id == $userId)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Drops a User from a Chat and removes the Chat if necessary
     *
     * @param $chatId
     * @param $userId
     * @return bool
     */
    public function dropUser($chatId, $userId)
    {
        if (!$chat = $this->findOne(['_id' => new ObjectID($chatId)]))
        {
            return false;
        }

        $chatUsers = $chat->users;

        foreach ($chatUsers ?? [] as $k => $userRow)
        {
            if ($userRow->id == $userId)
            {
                if (count($chatUsers) <= 2)
                {
                    // one of them were User => kill the whole chat
                    $this->delete($chat);
                }
                else
                {
                    unset ($chatUsers[$k]);
                    \Sys::svc('Chat')->update($chat, ['users' => $chatUsers]);
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Finds a Chat by emails of all its participants
     *
     * @param array $emails
     * @return mixed|null
     */
    public function findByEmails($emails = [])
    {
        $ids = [];

        foreach ($emails as $email)
        {
            if (!$user = \Sys::svc('User')->findOne(['email' => $email]))
            {
                return null;
            }

            $ids[] = $user->_id;
        }

        return $this->findOne(['users.id' => ['$all' => $ids]]);
    }
}
