<?php

namespace App\Service;

class Mailbox extends Generic
{
    /**
     * @param $userId
     * @return array
     */
    public function findByUserId($userId)
    {
        return \DB::rows('SELECT * FROM mailbox WHERE user_id = ?', [$userId]);
    }

    /**
     * If no code is present, i
     * 
     * @param null $code
     * @return array|string
     */
    public function getOAuthToken($code = null)
    {
        $client = new \Google_Client();
        $client->setApplicationName('Hollo App');
        $client->setClientId(\Sys::cfg('social_auth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('social_auth.google.secret'));
        $client->setRedirectUri('https://' . \Sys::cfg('mailless.app_domain') . '/oauth/google');
        $client->addScope('https://mail.google.com/');
        $client->addScope('https://www.googleapis.com/auth/userinfo.email');
        $client->addScope('https://www.googleapis.com/auth/plus.me');
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        if ($code)
        {
            $accessToken = $client->authenticate($code);
            $client->setAccessToken($accessToken);

            // use an access token to fetch email and picture
            $accessToken = json_decode($accessToken, true);

            $ch = curl_init('https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $accessToken['access_token']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = json_decode(curl_exec($ch), true) ?:[];
            curl_close($ch);

            return array
            (
                'refresh'   => $client->getRefreshToken(),
                'email'     => $data['email'],
                'avatar'    => $data['picture'],
            );
        }
        else
        {
            return $client->createAuthUrl();
        }
    }
    
    public function processOAuthCode($code)
    {
        $oauthData = $this->getOAuthToken($code);

        $token = $oauthData['token'];
        $email = $oauthData['email'];

        $user = \Auth::user();
        if (!$user->ext_id)
        {
            // create Context.IO account
            $data = $this->conn->addAccount(array
            (
                'email'                     => $email,
                'server'                    => '',
                'username'                  => $email,
                'use_ssl'                   => 1,
                'port'                      => '',
                'type'                      => 'IMAP',
                'provider_refresh_token'    => $token,
                'provider_consumer_key'     => \Sys::cfg('social_auth.google.clientId'),
            ));

            $data = $data->getData();

            $user->ext_id = $data['id'];
            \Sys::svc('User')->update($user);
            \Sys::svc('Auth')->sync();

            // add webhook to an existing account
            $this->conn->addWebhook($user->ext_id,
            [
                'callback_url'      => 'https://api.hollo.email/api/context-io',
                'failure_notif_url' => 'https://api.hollo.email/api/context-io',
            ]);
        }
        else
        {
            // just connect a source
            $this->conn->addSource($user->ext_id, array
            (
                'email'                     => $email,
                'server'                    => '',
                'username'                  => $email,
                'use_ssl'                   => 1,
                'port'                      => '',
                'type'                      => 'IMAP',
                'provider_refresh_token'    => $token,
                'provider_consumer_key'     => \Sys::cfg('social_auth.google.clientId'),
            ));
        }

        // create a mailbox
        $this->create(array
        (
            'user_id'   => \Auth::user()->id,
            'email'     => $email,
            'settings'  => json_encode(array
            (
                'token'     => $token,
                'service'   => 1,
            )),
        ));
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


        }
        else throw new \Exception('getConnectToken() error: no account');



        \Sys::svc('Resque')->addJob('SyncContacts', ['user_id' => $user->id]);

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

        // TODO: detect service by email

        $user = \Auth::user();

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
            $user->ext_id = $res['id'];
            \Sys::svc('User')->update($user);
            \Sys::svc('Auth')->sync();

            // add webhook
            $this->conn->addWebhook($user->ext_id,
            [
                'callback_url'      => 'https://api.hollo.email/api/context-io',
                'failure_notif_url' => 'https://api.hollo.email/api/context-io',
            ]);
        }

        $this->create(array
        (
            'user_id'   => \Auth::user()->id,
            'email'     => $email,
            'settings'  => json_encode(array
            (
                'server'        => $server,
                'username'      => $username,
                'use_ssl'       => $ssl,
                'password'      => $password,
                'port'          => $port,
                'type'          => 2,
            )),
        ));

        \Sys::svc('Resque')->addJob('SyncContacts', ['user_id' => $user->id]);

        return true;
    }
}
