<?php

namespace App\Service;

class Profile extends Generic
{
    protected $cache = [];

    /**
     * Finds a profile for a User by his ID
     *
     * @param $userId
     * @return array
     */
    public function findByUserId($userId)
    {
        if (!isset ($this->cache[$userId]))
        {
            $this->cache[$userId] = \DB::row('SELECT * FROM profile WHERE user_id = ? LIMIT 1', [$userId]);
        }

        return $this->cache[$userId];
    }
}
