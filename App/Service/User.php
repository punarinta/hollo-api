<?php

namespace App\Service;

class User extends Generic
{
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
     * Overwritten to insert Profile
     *
     * @param $data
     * @return \StdClass
     */
    public function create($data)
    {
        $user = parent::create($data);

        // TODO: create profile

        return $user;
    }

    /**
     * Adds an account to a user
     *
     * @param $email
     */
    public function addAccount($email)
    {
        // TODO: find out what is returned
        $this->conn->addAccount(['email' => $email]);
    }
}
