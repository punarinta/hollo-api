<?php

namespace App\Service;

use \App\Service\User as UserSvc;
use \App\Service\MailService as MailServiceSvc;
use MongoDB\BSON\ObjectID;

class Auth
{
    /**
     * Resynchronizes session
     *
     * @return bool
     */
    public static function sync()
    {
        // some checks
        if (!isset ($_SESSION['-AUTH']['user'])) return false;
        if (!$userId = $_SESSION['-AUTH']['user']->_id) return false;

        if (!$user = UserSvc::findOne(['_id' => new ObjectID($userId)]))
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
    public static function loginImap($user, $password)
    {
        // mail service is known
        if (!$in = MailServiceSvc::getCfg($user->settings->svc))
        {
            // bullshit, but we don't want to say 'unsupported'
            throw new \Exception('Mail service provider did not respond.');
        }

        // TODO support non-SSL login

        if (!$box = imap_open('{' . $in->host . ':' . $in->port . '/imap/ssl/novalidate-cert/readonly}', $user->email, $password))
        {
            throw new \Exception('Incorrect username or password.');
        }

        imap_close($box);

        $key = \Sys::cfg('sys.imap_hash');
        $user->settings->hash = openssl_encrypt($password, 'aes-256-cbc', $key, 0, $key);
        UserSvc::update($user);

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
    public static function registerImap($email, $password, $locale = 'en_US')
    {
        if ($mailService = MailService::findByEmail($email))
        {
            $in = MailService::getCfg($mailService);
        }
        else
        {
            if (!$mailService = MailService::fullDiscoverAndSave($email))
            {
                // bullshit, but we don't want to say 'unsupported'
                throw new \Exception('Mail service provider did not respond.');
            }

            $in = MailService::getCfg($mailService);
        }

        // TODO support non-SSL login

        if (!$box = imap_open('{' . $in->host . ':' . $in->port . '/imap/ssl/novalidate-cert/readonly}', $email, $password))
        {
            throw new \Exception('Incorrect username or password.');
        }

        imap_close($box);

        $created = time();
        $key = \Sys::cfg('sys.imap_hash');
        $settings = ['svc' => $mailService->_id, 'hash' => openssl_encrypt($password, 'aes-256-cbc', $key, 0, $key)];

        if ($locale != 'en_US')
        {
            $settings['locale'] = $locale;
        }

        // create Hollo account or use an existing dummy one
        if (!$user = User::findOne(['email' => $email]))
        {
            $user = User::create(array
            (
                // 'name' is unknown for a standard IMAP
                'email'     => $email,
                'roles'     => \Auth::USER,
                'created'   => $created,
                'settings'  => $settings,
            ));
        }
        else
        {
            // user exists and it's real, not good
        /*    if (isset ($user->roles))
            {
                throw new \Exception('User already exists');
            }*/
            $user->created = $created;
            $user->roles = \Auth::USER;
            $user->settings = $settings;
            User::update($user);
        }

        if (!$user->_id)
        {
            throw new \Exception('Cannot add user');
        }

        Resque::addJob('SyncContacts', ['user_id' => $user->_id]);


        $_SESSION['-AUTH']['user'] = $user;
        $_SESSION['-AUTH']['mail'] = ['user' => $user->email, 'pass' => $password];

        return $user;
    }

    /**
     * If no code is present, init
     *
     * @param null $code
     * @param null $redirectUrl
     * @return array|string
     */
    public static function getOAuthToken($code = null, $redirectUrl = null)
    {
        if (!$redirectUrl)
        {
            $redirectUrl = 'https://' . \Sys::cfg('mailless.app_domain') . '/'; //oauth/google';
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
            $accessToken = json_decode($accessToken, true);

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
    public static function processOAuthCode($code, $redirectUrl = null)
    {
        $oauthData = self::getOAuthToken($code, $redirectUrl);

        $token = $oauthData['refresh'];
        $email = strtolower($oauthData['email']);
        $avatar = $oauthData['avatar'];
        $name = $oauthData['name'];

        // Now we only have Google working with OAuth
        $mailService = MailService::findOne(['name' => 'Gmail']);
        $user = User::findOne(['email' => $email]);

        if ((!$user || !isset ($user->roles)) && $token)
        {
            $created = time();

            // no user -> register
            $settings = ['svc' => $mailService->_id, 'token' => $token];

            if (!$user)
            {
                // create Hollo account
                $user = User::create(array
                (
                    'name'      => $name,
                    'email'     => $email,
                    'roles'     => \Auth::USER,
                    'created'   => $created,
                    'settings'  => $settings,
                ));
            }
            else
            {
                if (!isset ($user->name))
                {
                    $user->name = $name;
                }
                $user->created = $created;
                $user->roles = \Auth::USER;
                $user->settings = $settings;
                User::update($user);
            }

            if (!$user->_id)
            {
                throw new \Exception('Cannot add user');
            }

            Resque::addJob('SyncContacts', ['user_id' => $user->_id]);
        }
        elseif ($token)
        {
            // save updated refresh token on every login
            $user->settings->token = $token;
            User::update($user);
        }

        $_SESSION['-AUTH']['user'] = $user;
        $_SESSION['-AUTH']['avatar'] = $avatar;

        if ($token)
        {
            $_SESSION['-AUTH']['mail'] = ['token' => $token];
        }
        elseif ($user->settings->token)
        {
            $_SESSION['-AUTH']['mail'] = ['token' => $user->settings->token];
        }
    }

    /**
     * Logs you out
     */
    public static function logout()
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
    public static function incarnate($userId, $forever = false, $skipCheck = false)
    {
        if (!\Auth::check() && !$skipCheck)
        {
            throw new \Exception('You need to be logged-in to incarnate.');
        }

        if (!$newUser = User::findOne(['_id' => $userId]))
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
    public static function wakeUp()
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
    public static function canWakeUp()
    {
        return isset ($_SESSION['-AUTH']['real-user']);
    }
}