<?php

namespace App\Service;

class Token extends Generic
{
    /**
     * Returns all the tokens for the specified login email
     *
     * @param $email
     * @return array
     */
    public function findByEmail($email)
    {
        return \DB::rows('SELECT * FROM token AS t LEFT JOIN user AS u ON u.context_id=t.context_id WHERE u.email=?', [$email]);
    }

    /**
     * Returns connection information for a token
     *
     * @param $token
     * @return array
     * @throws \Exception
     */
    public function checkToken($token)
    {
        $contextId = \Auth::check() ? \Auth::user()->context_id : null;

        if (!$tokenInfo = $this->conn->getConnectToken($contextId, ['token' => $token]))
        {
            throw new \Exception('Unknown access token');
        }

        $tokenInfo = $tokenInfo->getData();

        return array
        (
            'email'     => $tokenInfo['email'],
            'created'   => $tokenInfo['created'],
            'expires'   => $tokenInfo['expires'],
            'contextId' => @$tokenInfo['account']['id'],
        );
    }
}
