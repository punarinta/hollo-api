<?php

namespace App\Service;

use App\Model\ContextIO\ContextIO;

class Email
{
    public function __construct()
    {
        $cfg = \Sys::cfg('contextio');
        $this->conn = new ContextIO($cfg['key'], $cfg['secret']);
    }

    /**
     * @param $email
     * @param string $firstName
     * @param string $lastName
     * @return null
     * @throws \Exception
     */
    public function getOAuthToken($email, $firstName = 'No first name', $lastName = 'No last name')
    {
        $response = $this->conn->addConnectToken(\Auth::user()->ext_id,
        [
            'callback_url'  => 'https://' . \Sys::cfg('mailless.app_domain'),
            'email'         => $email,
            'first_name'    => $firstName,
            'last_name'     => $lastName
        ]);

        return $response->getDataProperty('browser_redirect_url');
    }

    /**
     * @param $token
     * @return bool
     * @throws \Exception
     */
    public function saveContextIdByToken($token)
    {
        // uncomment to allow only 1 email
        // if (\Auth::user()->ext_id)
        // {
        //    throw new \Exception('Email already attached');
        // }

        if (!$res = $this->conn->getConnectToken(\Auth::user()->ext_id, ['token' => $token]))
        {
            throw new \Exception('getConnectToken() error: false response');
        }

        $user = \Auth::user();
        $res = $res->getData();

        if (isset ($res['account']) && !$user->ext_id)
        {
            $user->ext_id = $res['account']['id'];
            \Sys::svc('User')->update($user);
            \Sys::svc('Auth')->sync();
        }
        else throw new \Exception('getConnectToken() error: no account');

        return true;
    }

    /**
     * Discovers IMAP settings for a specified email
     *
     * @param $email
     * @return array|bool
     */
    public function discoverEmail($email)
    {
        $r = $this->conn->discovery(array
        (
            'source_type'   => 'IMAP',
            'email'         => $email,
        ));

        if ($r = $r->getData())
        {
            if ($r['found'])
            {
                $r = $r['imap'];

                return array
                (
                    'ssl'       => $r['use_ssl'],
                    'port'      => $r['port'],
                    'oauth'     => $r['oauth'],
                    'server'    => $r['server'],
                    'username'  => $r['username'],
                );
            }
            else
            {
                return ['oauth' => false];
            }
        }

        return false;
    }

    /**
     * @param $email
     * @param $password
     * @param $server
     * @param int $port
     * @param null $username
     * @param int $ssl
     * @return bool
     * @throws \Exception
     */
    public function attachEmail($email, $password, $server, $port = 993, $username = null, $ssl = 1)
    {
        if (!$username)
        {
            $username = $email;
        }

        if (\Auth::user()->ext_id)
        {
            // uncomment to allow only 1 email
            // throw new \Exception('Email already attached');

            $this->conn->addSource(\Auth::user()->ext_id, array
            (
                'email'         => $email,
                'server'        => $server,
                'username'      => $username,
                'use_ssl'       => $ssl,
                'password'      => $password,
                'port'          => $port,
                'type'          => 'IMAP',
            ));
        }
        else
        {
            $res = $this->conn->addAccount(array
            (
                'email'         => $email,
                'server'        => $server,
                'username'      => $username,
                'use_ssl'       => $ssl,
                'password'      => $password,
                'port'          => $port,
                'type'          => 'IMAP',
            ));

            if (!$res)
            {
                throw new \Exception('Failed to add an a account');
            }

            $res = $res->getData();
            $user = \Auth::user();
            $user->ext_id = $res['id'];
            \Sys::svc('User')->update($user);
            \Sys::svc('Auth')->sync();
        }

        return true;
    }
}
