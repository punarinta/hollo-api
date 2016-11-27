<?php

namespace App\Service;

use MongoDB\BSON\ObjectID;

class Chat extends Generic
{
    /**
     * Initializes a new Chat with a specific set of participants
     *
     * @param array $emails
     * @param null $muting      - array of participating user IDs
     * @param array $names
     * @return bool|null|\StdClass
     * @throws \Exception
     */
    public function init($emails = [], $muting = null, $names = [])
    {
        $emails = array_unique($emails);

        // check just in case
        if ($chat = $this->findByEmails($emails))
        {
            return $chat;
        }

        \DB::begin();

        try
        {
            // create chat itself
            $chat = $this->create(array
            (
                'name'      => null,
                'last_ts'   => 0,
            ));

            $userIds = [];
            $muteThis = false;

            // assure that all users exist
            foreach ($emails as $email)
            {
                if (!$user = \Sys::svc('User')->findByEmail($email))
                {
                    // create a dummy user
                    $user = \Sys::svc('User')->create(array
                    (
                        'email'     => $email,
                        'name'      => (isset ($names[$email]) && $names[$email] != $email) ? $names[$email] : null,
                        'roles'     => \Auth::GUEST,
                        'settings'  => '',
                    ));
                }

                $userIds[] = $user->id;

                if (\Sys::svc('Contact')->isMuted($email))
                {
                    $muteThis = true;
                }
            }


            // link users into the chat
            foreach ($userIds as $userId)
            {
                if ($muteThis)
                {
                    // it's the reference person, mute this chat for him by default
                    $stmt = \DB::prepare('INSERT INTO chat_user (`chat_id`, `user_id`, `muted`) VALUES (?,?,?)', [$chat->id, $userId, 1]);
                }
                else
                {
                    $stmt = \DB::prepare('INSERT INTO chat_user (`chat_id`, `user_id`) VALUES (?,?)', [$chat->id, $userId]);
                }

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
            ['projection' => ['messages' => ['$slice' => -1]]]      // get last message only
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
     * Returns a Chat assuring User access.
     *
     * @param $id
     * @param $userId
     * @return null|\StdClass
     */
    public function findByIdAndUserId($id, $userId)
    {
        return \DB::row('SELECT c.* FROM chat AS c LEFT JOIN chat_user AS cu ON c.id=cu.chat_id WHERE c.id=? AND cu.user_id=? LIMIT 1', [$id, $userId]);
    }

    /**
     * Gets 'read' and 'muted' flags for given Chat and User
     *
     * @param $chatId
     * @param $userId
     * @return null|\StdClass
     */
    public function getFlags($chatId, $userId)
    {
        return \DB::row('SELECT `read`, muted FROM chat_user WHERE chat_id=? AND user_id=?', [$chatId, $userId]);
    }

    /**
     * Sets 'read' or/and 'muted' flags for given Chat and User
     *
     * @param $chatId
     * @param $userId
     * @param $flags
     */
    public function setFlags($chatId, $userId, $flags)
    {
        $stmt = \DB::prepare('UPDATE chat_user SET muted=?, `read`=? WHERE chat_id=? AND user_id=? LIMIT 1', [$flags->muted, $flags->read, $chatId, $userId]);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Sets 'muted' flag for given Chat and User
     *
     * @param $chatId
     * @param $userId
     * @param $muted
     */
    public function setMutedFlag($chatId, $userId, $muted)
    {
        $stmt = \DB::prepare('UPDATE chat_user SET muted=? WHERE chat_id=? AND user_id=? LIMIT 1', [$muted, $chatId, $userId]);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Sets 'read' flag for given Chat and User
     *
     * @param $chatId
     * @param $userId
     * @param $read
     */
    public function setReadFlag($chatId, $userId, $read)
    {
        if ($userId > 0)
        {
            $stmt = \DB::prepare('UPDATE chat_user SET `read`=? WHERE chat_id=? AND user_id=? LIMIT 1', [$read, $chatId, $userId]);
        }
        else if ($userId < 0)
        {
            $stmt = \DB::prepare('UPDATE chat_user SET `read`=? WHERE chat_id=? AND user_id!=?', [$read, $chatId, -$userId]);
        }
        else
        {
            $stmt = \DB::prepare('UPDATE chat_user SET `read`=? WHERE chat_id=?', [$read, $chatId]);
        }

        $stmt->execute();
        $stmt->close();
    }

    /**
     * Counts Users in the Chat
     *
     * @param $chatId
     * @return mixed
     */
    public function countUsers($chatId)
    {
        $x = \DB::row('SELECT count(0) AS c FROM chat_user WHERE chat_id=?', [$chatId]);

        return $x->c;
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
        foreach ($chat->users as $userItem)
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

        foreach ($chatUsers as $k => $userRow)
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
