<?php

namespace App\Service;

class Profile extends Generic
{
    /**
     * Finds a profile for a User by his ID
     *
     * @param $userId
     * @return array
     */
    public function findByUserId($userId)
    {
        return \DB::row('SELECT * FROM profile WHERE user_id = ? LIMIT 1', [$userId]);
    }
}
