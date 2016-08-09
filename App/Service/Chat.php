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
        $sql = 'SELECT * FROM chat WHERE user_id=?';
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
     * Finds a Chat by emails of all its participants
     *
     * @param array $emails
     * @param $userId
     * @return bool|null|\StdClass
     */
    public function findByEmailsAndUserId($emails = [], $userId)
    {
        $sql = 'SELECT * FROM chat AS c';
        $params = [$userId];
        $where = 'WHERE user_id=?';
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
