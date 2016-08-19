<?php

namespace App\Service;

class Chat extends Generic
{
    /**
     * Initializes a new Chat with a specific set of participants
     *
     * @param array $emails
     * @return mixed
     * @throws \Exception
     */
    public function init($emails = [])
    {
        $emails = array_unique($emails);

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
        $sql = 'SELECT * FROM chat AS c LEFT JOIN chat_user AS cu ON cu.chat_id=c.id WHERE cu.user_id=?';
        $params = [$userId];

        foreach ($filters as $filter)
        {
            switch ($filter['mode'])
            {
                case 'name':
                    $sql .= ' AND `name` LIKE ?';
                    break;

                case 'muted':
                    $sql .= ' AND muted=?';
                    break;
            }

            $params[] = $filter['value'];
        }

        if ($sortBy)
        {
            if ($sortBy == 'name')
            {
                $sql .= ' ORDER BY `read` ASC, `name`';
            }
            elseif ($sortBy == 'lastTs')
            {
                $sql .= ' ORDER BY `read` ASC, last_ts DESC';
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
     * Returns a Chat.
     *
     * @param $id
     * @param $userId
     * @return null|\StdClass
     */
    public function findByIdAndUserId($id, $userId)
    {
        return \DB::row('SELECT * FROM chat WHERE id=? AND user_id=? LIMIT 1', [$id, $userId]);
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

        if (\Sys::svc('Chat')->countUsers($chatId) < 2)
        {
            // there's only one person left, remove that link too
            $stmt = \DB::prepare('DELETE FROM chat_user WHERE chat_id=? LIMIT 1', [$chatId]);
            $stmt->execute();
            $stmt->close();

            // kill the chat
            \Sys::svc('Chat')->delete($chatId);

            return false;
        }

        return true;
    }

    /**
     * Finds a Chat by emails of all its participants
     *
     * @param array $emails
     * @return bool|null|\StdClass
     */
    public function findByEmails($emails = [])
    {
        $sql = 'SELECT * FROM chat AS c';
        $params = [];
        $where = ' WHERE 1=1';
        $counter = 0;

        foreach ($emails as $email)
        {
            if (!$user = \Sys::svc('User')->findByEmail($email))
            {
                // this user doesn't even exist
                return false;
            }

            $sql .= " LEFT JOIN chat_user AS cu$counter ON cu$counter.chat_id=c.id";
            $where .= ' AND cu$counter.user_id=?';
            $params[] = $user->id;

            ++$counter;
        }

        return $counter ? \DB::row($sql . $where, $params) : false;
    }
}
