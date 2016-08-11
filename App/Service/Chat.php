<?php

namespace App\Service;

class Chat extends Generic
{
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
