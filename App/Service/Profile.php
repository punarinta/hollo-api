<?php

namespace App\Service;

class Profile extends Generic
{
    /**
     * Finds a profile for a User by his ID
     *
     * @param $userId
     * @return null|\StdClass
     */
    public function findByUserId($userId)
    {
        return \DB::row('SELECT * FROM profile WHERE user_id = ? LIMIT 1', [$userId]);
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
        $profile = $this->findByUserId($userId);

        if (!$user || !$profile || !$user->ext_id)
        {
            throw new \Exception('Names sync is not possible');
        }

        $this->conn->modifyAccount($user->ext_id, array
        (
            'first_name' => $profile->first_name,
            'last_name'  => $profile->last_name,
        ));
    }
}
