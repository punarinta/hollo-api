<?php

namespace App\Service;

class User extends Generic
{
    protected $cache = [];

    /**
     * Overwritten creation to validate email
     *
     * @param $data
     * @return \StdClass
     * @throws \Exception
     */
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
     * @return array
     */
    public function findAllReal()
    {
        return \DB::rows('SELECT * FROM `user` WHERE roles > 0');
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
            return \DB::row('SELECT * FROM `user` WHERE email=? AND roles > 0 LIMIT 1', [$email]);
        }
        else
        {
            return \DB::row('SELECT * FROM `user` WHERE email=? LIMIT 1', [$email]);
        }
    }

    /**
     * Returns all the users participating in the Chat
     *
     * @param $chatId
     * @param bool $forContacts
     * @param int $exceptId
     * @return array
     */
    public function findByChatId($chatId, $forContacts = false, $exceptId = 0)
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

    /**
     * Update a GMail subscription for a User
     *
     * @param $user
     * @return bool
     */
    public function subscribeToGmail($user)
    {
        $settings = json_decode($user->settings, true) ?: [];

        if ($settings['svc'] != 1)
        {
            return false;
        }

        if (!$token = $settings['token'])
        {
            return false;
        }

        $client = new \Google_Client();
        $client->setClientId(\Sys::cfg('oauth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('oauth.google.secret'));
        $client->refreshToken($token);
        $accessToken = $client->getAccessToken();
        $accessToken = json_decode($accessToken, true);

        $ch = curl_init('https://www.googleapis.com/gmail/v1/users/me/watch?oauth_token=' . $accessToken['access_token']);

        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array
        (
            'topicName' => 'projects/hollo-1271/topics/gmail',
            'labelIds'  => ['INBOX'],
        )));
        $res = curl_exec($ch);
        curl_close($ch);

        if (@$GLOBALS['-SYS-VERBOSE'])
        {
            print_r($res);
        }

        $settings['historyId'] = $res['historyId'];
        $user->settings = json_encode($settings);
        \Sys::svc('User')->update($user);

        return true;
    }
}
