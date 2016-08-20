<?php

namespace App\Service;

class User extends Generic
{
    protected $cache = [];

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
     * @return array
     */
    public function findByEmail($email)
    {
        return \DB::row('SELECT * FROM user WHERE email = ? LIMIT 1', [$email]);
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
     * @return array
     */
    public function findByChatId($chatId, $forContacts = false)
    {
        if ($forContacts)
        {
            // skip yourself, feed only basic data
            return \DB::rows('SELECT id, email, `name` FROM user AS u LEFT JOIN chat_user AS cu ON cu.user_id=u.id WHERE cu.chat_id=? AND u.id!=?', [$chatId, \Auth::user()->id]);
        }
        else
        {
            return \DB::rows('SELECT * FROM user AS u LEFT JOIN chat_user AS cu ON cu.user_id=u.id WHERE cu.chat_id=?', [$chatId]);
        }
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
        $user = \Sys::svc('User')->findById($userId);
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
            $user = \Sys::svc('User')->findById($user);
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
