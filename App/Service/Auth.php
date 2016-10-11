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
     * @throws \Exception
     */
    public function loginImap($user, $password)
    {
        $cfg = json_decode($user->settings, true) ?:[];

        // mail service is known
        if (!$in = \Sys::svc('MailService')->getCfg($cfg['svc']))
        {
            // bullshit, but we don't want to say 'unsupported'
            throw new \Exception('Mail service provider did not respond.');
        }

        // TODO support non-SSL login

        if (!$box = imap_open('{' . $in['host'] . ':' . $in['port'] . '/imap/ssl/novalidate-cert/readonly}', $user->email, $password))
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
        if ($mailService = \Sys::svc('MailService')->findByEmail($email))
        {
            $in = \Sys::svc('MailService')->getCfg($mailService);
        }
        else
        {
            if (!$mailService = \Sys::svc('MailService')->fullDiscoverAndSave($email))
            {
                // bullshit, but we don't want to say 'unsupported'
                throw new \Exception('Mail service provider did not respond.');
            }

            $in = \Sys::svc('MailService')->getCfg($mailService);
        }

        // TODO support non-SSL login

        if (!$box = imap_open('{' . $in['host'] . ':' . $in['port'] . '/imap/ssl/novalidate-cert/readonly}', $email, $password))
        {
            throw new \Exception('Incorrect username or password.');
        }

        imap_close($box);
        
        $settings = ['svc' => (int) $mailService->id/*, 'email' => $email, 'password' => $password*/];
        
        if ($locale != 'en_US')
        {
            $settings['locale'] = $locale;
        }
        
        \DB::begin();
        
        try
        {
            // create Hollo account or use an existing dummy one
            if (!$user = \Sys::svc('User')->findByEmail($email))
            {
                $user = \Sys::svc('User')->create(array
                (
                    'email'     => $email,
                    'ext_id'    => null,
                    'roles'     => \Auth::USER,
                    'settings'  => json_encode($settings),
                ));
            }
            else
            {
                // user exists and it's real, not good
                if ($user->ext_id)
                {
                    throw new \Exception('Cannot add user');
                }
                $user->settings = json_encode($settings);
                $user->roles = \Auth::USER;
            }

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
                'callback_url'      => 'https://app.hollo.email/api/context-io',
                'failure_notif_url' => 'https://app.hollo.email/api/context-io',
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
     * @param null $redirectUrl
     * @return array|string
     */
    public function getOAuthToken($code = null, $redirectUrl = null)
    {
        include_once 'vendor/guzzlehttp/psr7/src/functions.php';
        include_once 'vendor/guzzlehttp/guzzle/src/functions.php';
        include_once 'vendor/guzzlehttp/promises/src/functions.php';

        if (!$redirectUrl)
        {
            $redirectUrl = 'https://' . \Sys::cfg('mailless.app_domain') . '/oauth/google';
        }

        $client = new \Google_Client();
        $client->setApplicationName('Hollo App');
        $client->setClientId(\Sys::cfg('oauth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('oauth.google.secret'));
        $client->setRedirectUri($redirectUrl);
        $client->addScope('https://mail.google.com/');
        $client->addScope('https://www.googleapis.com/auth/userinfo.email');
        $client->addScope('https://www.googleapis.com/auth/userinfo.profile');
        $client->addScope('https://www.googleapis.com/auth/plus.me');
        $client->addScope('https://www.google.com/m8/feeds');
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        if ($code)
        {
            $accessToken = $client->authenticate($code);
            $client->setAccessToken($accessToken);

            $ch = curl_init('https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $accessToken['access_token']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = json_decode(curl_exec($ch), true) ?:[];
            curl_close($ch);

            return array
            (
                'refresh'   => $client->getRefreshToken(),
                'email'     => $data['email'],
                'name'      => $data['name'],
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
     * @param null $redirectUrl
     * @throws \Exception
     */
    public function processOAuthCode($code, $redirectUrl = null)
    {
        $oauthData = $this->getOAuthToken($code, $redirectUrl);

        $token = $oauthData['refresh'];
        $email = $oauthData['email'];
        $avatar = $oauthData['avatar'];
        $name = $oauthData['name'];

        // $mailService = \Sys::svc('MailService')->findByEmail($email);
        // Now we only have Google working with OAuth
        $mailService = \Sys::svc('MailService')->findById(1);
        $in = \Sys::svc('MailService')->getCfg($mailService);

        $user = \Sys::svc('User')->findByEmail($email);

        if (!$user || !$user->ext_id)
        {
            // no user -> register
            $settings = ['svc' => (int) $mailService->id, 'token' => $token];

            \DB::begin();

            try
            {
                if (!$user)
                {
                    // create Hollo account
                    $user = \Sys::svc('User')->create(array
                    (
                        'name'      => $name,
                        'email'     => $email,
                        'ext_id'    => null,
                        'roles'     => \Auth::USER,
                        'settings'  => json_encode($settings),
                    ));
                }
                else
                {
                    $user->roles = \Auth::USER;
                    $user->settings = json_encode($settings);
                }

                if (!$user->id)
                {
                    throw new \Exception('Cannot add user');
                }

                // create Context.IO account
                $data = $this->conn->addAccount(array
                (
                    'email'                     => $email,
                    'server'                    => $in['host'],
                    'port'                      => $in['port'],
                    'username'                  => $email,
                    'use_ssl'                   => 1,
                    'type'                      => 'IMAP',
                    'provider_refresh_token'    => $token,
                    'provider_consumer_key'     => \Sys::cfg('oauth.google.clientId'),
                ));

                $data = $data->getData();
                $user->ext_id = $data['id'];

                // save token, just in case
                $s = \Sys::svc('User')->setting($user);
                $s['token'] = $token;
                $user->settings = json_encode($s);

                \Sys::svc('User')->update($user);

                // add webhook to an existing account
                $this->conn->addWebhook($user->ext_id,
                [
                    'callback_url'      => 'https://app.hollo.email/api/context-io',
                    'failure_notif_url' => 'https://app.hollo.email/api/context-io',
                ]);
            }
            catch (\Exception $e)
            {
                \DB::rollback();
            }

            \DB::commit();

            \Sys::svc('Resque')->addJob('SyncContacts', ['user_id' => $user->id]);
        }
        else
        {
            if (empty ($this->conn->listSources($user->ext_id, ['status_ok' => 1])->getData()))
            {
                /*$this->conn->deleteSource($user->ext_id, ['label' => 0]);

                $this->conn->addSource($user->ext_id, array
                (
                    'email'                     => $email,
                    'server'                    => $in['host'],
                    'port'                      => $in['port'],
                    'username'                  => $email,
                    'use_ssl'                   => 1,
                    'type'                      => 'IMAP',
                    'provider_refresh_token'    => $token,
                    'provider_consumer_key'     => \Sys::cfg('oauth.google.clientId'),
                ));*/

                $this->conn->post($user->ext_id, 'sources/0', array
                (
                    'status'                    => 1,
                    'provider_refresh_token'    => $token,
                    'provider_consumer_key'     => \Sys::cfg('oauth.google.clientId'),
                ));
            }

            // save updated refresh token on every login
            $settings = json_decode($user->settings, true) ?: [];
            $settings['token'] = $token;
            $user->settings = json_encode($settings);
            \Sys::svc('User')->update($user);
        }

        $_SESSION['-AUTH']['user'] = $user;
        $_SESSION['-AUTH']['mail'] = ['token' => $token];
        $_SESSION['-AUTH']['avatar'] = $avatar;
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