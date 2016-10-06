<?php

namespace App\Service;

class User extends Generic
{
    protected $cache = [];

    public function create($data)
    {
        if (filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false)
        {
            throw new \Exception('Cannot add user: invalid email');
        }

        return parent::create($data);
    }

    /**
     * Overwritten as User uses custom ID field name
     *
     * @param $id
     * @param bool $reRead
     * @return mixed
     */
    public function findById($id, $reRead = false)
    {
        if (!isset ($this->cache[$id]) || $reRead)
        {
            $this->cache[$id] = \DB::row('SELECT * FROM user WHERE id = ? LIMIT 1', [$id]);
        }

        return $this->cache[$id];
    }

    /**
     * Gets a User by its email
     *
     * @param $email
     * @param bool $realOnly
     * @return null|\StdClass
     */
    public function findByEmail($email, $realOnly = false)
    {
        if ($realOnly)
        {
            return \DB::row('SELECT * FROM `user` WHERE email=? AND ext_id IS NOT NULL LIMIT 1', [$email]);
        }
        else
        {
            return \DB::row('SELECT * FROM `user` WHERE email=? LIMIT 1', [$email]);
        }
    }

    /**
     * Gets a User by its external ID
     *
     * @param $extId
     * @return array
     */
    public function findByExtId($extId)
    {
        return \DB::row('SELECT * FROM user WHERE ext_id = ? LIMIT 1', [$extId]);
    }

    /**
     * Returns all the users participating in the Chat
     *
     * @param $chatId
     * @param bool $forContacts
     * @param null $exceptId
     * @return array
     */
    public function findByChatId($chatId, $forContacts = false, $exceptId = null)
    {
        if ($forContacts)
        {
            // skip yourself, feed only basic data
            return \DB::rows('SELECT id, email, `name` FROM user AS u LEFT JOIN chat_user AS cu ON cu.user_id=u.id WHERE cu.chat_id=? AND u.id!=?', [$chatId, $exceptId]);
        }
        else
        {
            return \DB::rows('SELECT * FROM user AS u LEFT JOIN chat_user AS cu ON cu.user_id=u.id WHERE cu.chat_id=?', [$chatId]);
        }
    }

    /**
     * Lists Users known by specified User
     *
     * @param $userId
     * @param array $filters
     * @return array
     */
    public function findKnownBy($userId, $filters = [])
    {
        $sql = 'SELECT DISTINCT u.id, u.email, u.name FROM `user` AS u 
                LEFT JOIN chat_user AS cu1 ON cu1.user_id = u.id
                LEFT JOIN chat AS c ON c.id = cu1.chat_id
                LEFT JOIN chat_user AS cu2 ON cu2.chat_id = c.id
                WHERE cu2.user_id = ?';

        $params = [$userId];

        foreach ($filters as $filter)
        {
            switch ($filter['mode'])
            {
                case 'email':
                    $sql .= ' AND u.email LIKE ?';
                    $filter['value'] = '%' . $filter['value'] . '%';
                    break;
            }

            $params[] = $filter['value'];
        }

        $sql .= ' ORDER BY u.email';

        return \DB::rows($sql, $params);
    }

    /**
     * Checks if one User is known by another User
     *
     * @param $user1
     * @param $user2
     * @return bool
     */
    public function isKnownBy($user1, $user2)
    {
        $sql = 'SELECT 1 FROM chat_user AS cu1
                LEFT JOIN chat AS c ON c.id = cu1.chat_id
                LEFT JOIN chat_user AS cu2 ON cu2.chat_id = c.id
                WHERE cu1.user_id = ? AND cu2.user_id = ? LIMIT 1';

        return (bool) \DB::row($sql, [$user1, $user2]);
    }

    /**
     * Checks if one User is known by another User via first one's email
     *
     * @param $email
     * @param $user
     * @return bool
     */
    public function isKnownByEmail($email, $user)
    {
        $sql = 'SELECT 1 FROM user AS u
                LEFT JOIN chat_user AS cu1 ON cu1.user_id = u.id
                LEFT JOIN chat AS c ON c.id = cu1.chat_id
                LEFT JOIN chat_user AS cu2 ON cu2.chat_id = c.id
                WHERE u.email = ? AND cu2.user_id = ? LIMIT 1';

        return (bool) \DB::row($sql, [$email, $user]);
    }

    /**
     * Adds an account to a user
     *
     * @param $email
     * @return mixed
     * @throws \Exception
     */
    public function addAccount($email)
    {
        // TODO: add more params for an account
        $res = $this->conn->addAccount(['email' => $email])->getData();

        if (!@$res['success'])
        {
            throw new \Exception('Cannot add account');
        }

        return $res['id'];
    }

    /**
     * Syncs remote account name with the local one
     *
     * @param $userId
     * @throws \Exception
     */
    public function syncNames($userId)
    {
        $user = $this->findById($userId);
        $settings = json_decode($user->settings);

        if (!$user || !$user->ext_id)
        {
            throw new \Exception('Names sync is not possible');
        }

        $this->conn->modifyAccount($user->ext_id, array
        (
            'first_name' => $settings->firstName,
            'last_name'  => $settings->lastName,
        ));
    }

    /**
     * Trigger an external mail sync
     * 
     * @param $user
     * @return null
     * @throws \Exception
     */
    public function syncExt($user)
    {
        if (!is_object($user))
        {
            $user = $this->findById($user);
        }

        if (!$user || !$user->ext_id)
        {
            throw new \Exception('Sync is not possible');
        }

        $res = $this->conn->syncSource($user->ext_id, ['label' => 0]);

        return $res ? $res->getData() : null;
    }

    /**
     * Returns a particular User setting
     *
     * @param $user
     * @param null $path
     * @return null
     * @throws \Exception
     */
    public function setting($user = null, $path = null)
    {
        if (!$user)
        {
            $user = \Auth::user();
        }
        else if (!is_object($user))
        {
            if (!$user = $this->findById($user))
            {
                throw new \Exception('User does not exist');
            }
        }

        return \Sys::aPath(json_decode(@$user->settings, true) ?:[], $path);
    }

    /**
     * Returns User's name
     *
     * @param $user
     * @return mixed
     * @throws \Exception
     */
    public function name($user = null)
    {
        if (!$user)
        {
            $user = \Auth::user();
        }
        else if (!is_object($user))
        {
            if (!$user = $this->findById($user))
            {
                throw new \Exception('User does not exist');
            }
        }

        $s = json_decode(@$user->settings, true) ?:[];

        return trim(@$s['firstName'] . ' ' . @$s['lastName']);
    }
}
