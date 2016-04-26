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
}
