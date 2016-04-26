<?php

namespace App\Service;

use App\Model\ContextIO\ContextIO;

class Auth
{
    protected $conn   = null;
    protected $secure = true;

    public function __construct()
    {
        $cfg = \Sys::cfg('contextio');
        $this->conn = new ContextIO($cfg['key'], $cfg['secret']);
    }

    /**
     * Returns a current logged-in user
     *
     * @return null
     */
    public function user()
    {
        if (isset ($_SESSION['-AUTH']['user']) && isset ($_SESSION['-AUTH']['user']->id))
        {
            return $_SESSION['-AUTH']['user'];
        }

        return null;
    }

    /**
     * Resynchronizes session
     *
     * @return bool
     */
    public function sync()
    {
        // some checks
        if (!isset ($_SESSION['-AUTH']['user'])) return false;
        if (!$userId = $_SESSION['-AUTH']['user']->id) return false;

        if (!$user = \Sys::svc('User')->findById($userId))
        {
            return false;
        }

        $_SESSION['-AUTH']['user'] = $user;

        return true;
    }

    /**
     * Login via IMAP
     *
     * @param $user
     * @param $password
     * @return mixed
     * @throws \Exception
     */
    public function loginImap($user, $password)
    {
        $cfg = json_decode($user->settings, true) ?:[];

        // mail service is known
        $in = \Sys::svc('MailService')->getCfg($cfg['svc']);

        if (!$box = imap_open('{' . $in['host'] . ':' . $in['port'] . '}', $user->email, $password))
        {
            throw new \Exception('Incorrect username or password.');
        }

        imap_close($box);

        $_SESSION['-AUTH']['user'] = $user;
        $_SESSION['-AUTH']['mail'] = ['user' => $user->email, 'pass' => $password];
    }

    /**
     * Registers via IMAP
     *
     * @param $email
     * @param string $password
     * @param string $locale
     * @return mixed
     * @throws \Exception
     */
    public function registerImap($email, $password, $locale = 'en_US')
    {
        $mailService = \Sys::svc('MailService')->findByEmail($email);
        $in = \Sys::svc('MailService')->getCfg($mailService);

        if (!$box = imap_open('{' . $in['host'] . ':' . $in['port'] . '}', $email, $password))
        {
            throw new \Exception('Incorrect username or password.');
        }

        imap_close($box);
        
        $settings = ['svc' => $mailService->id];
        
        if ($locale != 'en_US')
        {
            $settings['locale'] = $locale;
        }
        
        \DB::begin();
        
        try
        {
            // create Hollo account
            $user = \Sys::svc('User')->create(array
            (
                'email'     => $email,
                'ext_id'    => null,
                'roles'     => \Auth::USER,
                'created'   => time(),
                'settings'  => $settings,
            ));

            if (!$user->id)
            {
                throw new \Exception('Cannot add user');
            }

            // create Context account
            $res = $this->conn->addAccount(array
            (
                'email'         => $email,
                'server'        => $in['host'],
                'username'      => $email,
                'use_ssl'       => $in['enc'] != 'no',
                'password'      => $password,
                'port'          => $in['port'],
                'type'          => 'IMAP',
            ));
            
            if (!$res)
            {
                throw new \Exception('Cannot add account');
            }

            $res = $res->getData();
            $user->ext_id = $res['id'];
            \Sys::svc('User')->update($user);

            // add Context web-hook
            $this->conn->addWebhook($user->ext_id,
            [
                'callback_url'      => 'https://api.hollo.email/api/context-io',
                'failure_notif_url' => 'https://api.hollo.email/api/context-io',
            ]);
        }
        catch (\Exception $e)
        {
            \DB::rollback();
            throw $e;
        }

        \DB::commit();

        $_SESSION['-AUTH']['user'] = $user;
        $_SESSION['-AUTH']['mail'] = ['user' => $user->email, 'pass' => $password];

        \Sys::svc('Resque')->addJob('SyncContacts', ['user_id' => $user->id]);

        return $user;
    }

    /**
     * If no code is present, init
     *
     * @param null $code
     * @return array|string
     */
    public function getOAuthToken($code = null)
    {
        $client = new \Google_Client();
        $client->setApplicationName('Hollo App');
        $client->setClientId(\Sys::cfg('oauth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('oauth.google.secret'));
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

    /**
     * @param $code
     */
    public function processOAuthCode($code)
    {
        $oauthData = $this->getOAuthToken($code);

        $token = $oauthData['token'];
        $email = $oauthData['email'];
        // $avatar = $oauthData['avatar'];

        if (!$user = \Sys::svc('User')->findByEmail($email))
        {
            // no user -> register

            $mailService = \Sys::svc('MailService')->findByEmail($email);
            $settings = ['svc' => $mailService->id];

            \DB::begin();

            try
            {
                // create Hollo account
                $user = \Sys::svc('User')->create(array
                (
                    'email'     => $email,
                    'ext_id'    => null,
                    'roles'     => \Auth::USER,
                    'created'   => time(),
                    'settings'  => $settings,
                ));

                if (!$user->id)
                {
                    throw new \Exception('Cannot add user');
                }

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
                    'provider_consumer_key'     => \Sys::cfg('oauth.google.clientId'),
                ));

                $data = $data->getData();
                $user->ext_id = $data['id'];

                // save token, just in case
                // TODO: register, log out, login, post.
                $s = \Sys::svc('User')->setting();
                $s['token'] = $token;
                $user->settings = json_encode($s);

                \Sys::svc('User')->update($user);

                // add webhook to an existing account
                $this->conn->addWebhook($user->ext_id,
                [
                    'callback_url'      => 'https://api.hollo.email/api/context-io',
                    'failure_notif_url' => 'https://api.hollo.email/api/context-io',
                ]);
            }
            catch (\Exception $e)
            {
                \DB::rollback();
            }

            \DB::commit();

            \Sys::svc('Resque')->addJob('SyncContacts', ['user_id' => $user->id]);
        }

        $_SESSION['-AUTH']['user'] = $user;
        $_SESSION['-AUTH']['mail'] = ['token' => $token];
    }


    /**
     * Logs you out
     */
    public function logout()
    {
        unset ($_SESSION['-AUTH']);
    }

    /**
     * Incarnates you as a user
     *
     * @param $userId
     * @param bool $forever
     * @param bool $skipCheck
     * @throws \Exception
     */
    public function incarnate($userId, $forever = false, $skipCheck = false)
    {
        if (!\Auth::check() && !$skipCheck)
        {
            throw new \Exception('You need to be logged-in to incarnate.');
        }

        if (!$newUser = \Sys::svc('User')->findById($userId))
        {
            throw new \Exception('User not found. ID = ' . $userId);
        }

        if (!$forever)
        {
            $_SESSION['-AUTH']['real-user'] = $_SESSION['-AUTH']['user'];
        }

        $_SESSION['-AUTH']['user'] = $newUser;
    }

    /**
     * Wakes you up from incarnation
     *
     * @throws \Exception
     */
    public function wakeUp()
    {
        if (!isset ($_SESSION['-AUTH']['real-user']))
        {
            throw new \Exception('No real user exist. You will never get back...');
        }

        $_SESSION['-AUTH']['user'] = $_SESSION['-AUTH']['real-user'];

        unset ($_SESSION['-AUTH']['real-user']);
    }

    /**
     * Tells if you can wake up
     *
     * @return bool
     */
    public function canWakeUp()
    {
        return isset ($_SESSION['-AUTH']['real-user']);
    }
}