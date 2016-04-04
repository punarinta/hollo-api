<?php

namespace App\Service;

use App\Model\Bcrypt;
use App\Model\ContextIO\ContextIO;

class Auth
{
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
     * Returns a profile for the current logged-in user
     *
     * @return null
     */
    public function profile()
    {
        if (isset ($_SESSION['-AUTH']['profile']) && isset ($_SESSION['-AUTH']['profile']->id))
        {
            return $_SESSION['-AUTH']['profile'];
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
        if (!$userId = $_SESSION['-AUTH']['user']['id']) return false;

        if (!$user = \Sys::svc('User')->findById($userId))
        {
            return false;
        }

        $_SESSION['-AUTH']['user'] = $user;
        $_SESSION['-AUTH']['profile'] = \Sys::svc('Profile')->findByUserId($userId);

        return true;
    }

    /**
     * @param $username
     * @param $password
     * @return mixed
     * @throws \Exception
     */
    public function login($username, $password)
    {
        // we're logging in by an email
        if (!$user = \Sys::svc('User')->findByEmail($username))
        {
            throw new \Exception('Incorrect username or password.');
        }

        $crypt = new Bcrypt();
        $crypt->setCost(\Sys::cfg('sys.password_cost'));

        // check password
        if (!$crypt->verify($password, $user->password))
        {
            throw new \Exception('Incorrect username or password.');
        }

        $_SESSION['-AUTH']['user'] = $user;
        $_SESSION['-AUTH']['profile'] = \Sys::svc('Profile')->findByUserId($user->id);
    }

    /**
     * @param $email
     * @param string $firstName
     * @param string $lastName
     * @return null
     */
    public function getOAuthToken($email, $firstName = 'No first name', $lastName = 'No last name')
    {
        $url = 'https://' . \Sys::cfg('mailless.app_domain');

        // if you're not logged in, use 'addAccount', otherwise 'addSource'

        $response = $this->conn->addConnectToken(null,
        [
            'callback_url'  => 'https://api.hollo.dev/test-me/index.html',  // $url
            'email'         => $email,
            'first_name'    => $firstName,
            'last_name'     => $lastName
        ]);

        return $response->getDataProperty('browser_redirect_url');
    }

    /**
     * Returns context_id by token
     *
     * @param $token
     */
    public function processOAuthToken($token)
    {
        $getTokenResponse = $this->conn->getConnectToken($token);
        $user = $getTokenResponse->getDataProperty('user');

        return $user['id'];
    }

    /**
     * @param $email
     * @param string $password
     * @param null $server
     * @param string $firstName
     * @param string $lastName
     * @param string $lang
     * @param bool $login
     * @return mixed
     * @throws \Exception
     */
    public function attachAccount($email, $password = '', $server = null, $firstName = '', $lastName = '', $lang = 'en_US', $login = false)
    {
        $discovered = $this->conn->discovery(array
        (
            'source_type'   => 'IMAP',
            'email'         => 'email',
        ));

        $ssl = 1;
        $port = 993;
        $username = $email;

        if ($discovered)
        {
            $discovered = $discovered->getData();
            $port = $discovered['imap']['port'];
            $ssl = $discovered['imap']['use_ssl'];
            $username = $discovered['imap']['username'];
            $server = $server ?: $discovered['imap']['server'];
        }

        $res = $this->conn->addAccount(array
        (
            'first_name'    => $firstName,
            'last_name'     => $lastName,
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
        $accountId = $res['id'];

        $crypt = new Bcrypt;
        $password = $crypt->create($password);

        $user = \Sys::svc('User')->create(array
        (
            'email'         => $email,
            'password'      => $password,
            'context_id'    => $accountId,
            'roles'         => \Auth::USER,
            'locale'        => $lang,
            'created'       => \Time::now(),
        ));

        if (!$user->id)
        {
            throw new \Exception('Cannot add user');
        }

        // profile and role are created automatically, just fill in some stuff
        $profile = \Sys::svc('Profile')->findByUserId($user->id, true);

        /*    $profile->firstname = $firstName;
            $profile->lastname  = $lastName;
            \Sys::svc('Profile')->update($profile);*/

        if ($login)
        {
            $_SESSION['-AUTH']['user'] = $user;
            $_SESSION['-AUTH']['profile'] = $profile;
        }

        return $user;
    }

    /**
     * Registers a new user
     *
     * @param $email
     * @param string $password
     * @param string $firstName
     * @param string $lastName
     * @param string $lang
     * @param bool $login
     * @return mixed
     * @throws \Exception
     */
    public function register($email, $password, $firstName = '', $lastName = '', $lang = 'en_US', $login = false)
    {
        $crypt = new Bcrypt;
        $password = $crypt->create($password);

        $user = \Sys::svc('User')->create(array
        (
            'email'         => $email,
            'password'      => $password,
            'context_id'    => null,
            'roles'         => \Auth::USER,
            'locale'        => $lang,
            'created'       => time(),
        ));

        if (!$user->id)
        {
            throw new \Exception('Cannot add user');
        }

        // profile and role are created automatically, just fill in some stuff
        $profile = \Sys::svc('Profile')->findByUserId($user->id, true);

        $profile->first_name = $firstName;
        $profile->last_name  = $lastName;
        \Sys::svc('Profile')->update($profile);

        if ($login)
        {
            $_SESSION['-AUTH']['user'] = $user;
            $_SESSION['-AUTH']['profile'] = $profile;
        }

        return $user;
    }

    /**
     * Changes a password for a user
     *
     * @param $userId
     * @param $password
     * @param $passwordVerify
     * @throws \Exception
     */
    public function changePassword($userId, $password, $passwordVerify)
    {
        if ($password !== $passwordVerify)
        {
            throw new \Exception(\Lang::translate('Password verification is wrong.'));
        }

        if (!isset ($password[5]))
        {
            throw new \Exception('Password should be longer than 5 symbols');
        }

        $user = \Sys::svc('User')->findById($userId);

        $crypt = new Bcrypt;
        $user->password = $crypt->create($password);
        $user->modified = \Time::now();
        \Sys::svc('User')->update($user);
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
        $_SESSION['-AUTH']['profile'] = \Sys::svc('Profile')->findByUserId($newUser->id);
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
        $_SESSION['-AUTH']['profile'] = \Sys::svc('Profile')->findByUserId($_SESSION['-AUTH']['real-user']->id);

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