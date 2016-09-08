<?php

namespace App\Service;

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
                        'ext_id'    => null,
                        'roles'     => \Auth::USER,
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
        $sql = 'SELECT c.* FROM chat AS c LEFT JOIN chat_user AS cu ON cu.chat_id=c.id WHERE cu.user_id=?';

        // TODO: rewrite in a proper way
        if (@$filters[0]['mode'] == 'email' || @$filters[1]['mode'] == 'email')
        {
            $sql = 'SELECT DISTINCT c.id, c.* FROM chat AS c
                    LEFT JOIN chat_user AS cu ON cu.chat_id = c.id
                    LEFT JOIN chat_user AS cu2 ON cu2.chat_id = c.id
                    LEFT JOIN `user` AS u ON cu2.user_id = u.id
                    WHERE cu.user_id=?';
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

        return \DB::rows($sql, $params);
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
        $stmt = \DB::prepare('UPDATE chat_user SET `read`=? WHERE chat_id=? AND user_id=? LIMIT 1', [$read, $chatId, $userId]);
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
     * @param $chatId
     * @param $userId
     * @return bool
     */
    public function hasAccess($chatId, $userId)
    {
        return (bool) \DB::row('SELECT 1 FROM chat_user WHERE chat_id=? AND user_id=?', [$chatId, $userId]);
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
        // remove the specified user first
        $stmt = \DB::prepare('DELETE FROM chat_user WHERE chat_id=? AND user_id=? LIMIT 1', [$chatId, $userId]);
        $stmt->execute();
        $stmt->close();

        if ($this->countUsers($chatId) < 2)
        {
            // there's only one person left, remove that link too
            $stmt = \DB::prepare('DELETE FROM chat_user WHERE chat_id=? LIMIT 1', [$chatId]);
            $stmt->execute();
            $stmt->close();

            // kill the chat
            $this->delete($chatId);

            return false;
        }

        return true;
    }

    /**
     * Finds a Chat by emails of all its participants
     *
     * @param array $emails
     * @return bool|null
     */
    public function findByEmails($emails = [])
    {
        $sql = 'SELECT c.* FROM chat AS c';
        $params = [];
        $where = ' WHERE 1=1';
        $counter = 0;
        $emailCount = count($emails);

        foreach ($emails as $email)
        {
            if (!$user = \Sys::svc('User')->findByEmail($email))
            {
                // this user doesn't even exist
                return false;
            }

            $sql .= " LEFT JOIN chat_user AS cu{$counter} ON cu{$counter}.chat_id=c.id";
            $where .= " AND cu{$counter}.user_id=?";
            $params[] = $user->id;

            ++$counter;
        }

        try
        {
            if ($counter)
            {
                foreach (\DB::rows($sql . $where, $params) as $chat)
                {
                    if ($this->countUsers($chat->id) == $emailCount)
                    {
                        return $chat;
                    }
                }
            }
        }
        catch (\Exception $e)
        {
            echo "Strange things happen: " . $e->getMessage();
        }

        return null;
    }
}
