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
