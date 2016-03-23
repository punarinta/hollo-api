<?php

namespace App\Service;

use App\Model\Bcrypt;

class Auth
{
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

        // set 'last_login'
        $user->last_login = \Time::now();

        // update the user
        \Sys::svc('User')->update($user);

        $_SESSION['-AUTH']['user'] = $user;
        $_SESSION['-AUTH']['profile'] = \Sys::svc('Profile')->findByUserId($user->id);
    }

    /**
     * Registers a new user
     *
     * @param $email
     * @param $password
     * @param string $firstName
     * @param string $lastName
     * @param string $lang
     * @param bool|false $login
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
            'display_name'  => ($firstName || $lastName) ? trim($firstName . ' ' . $lastName) : $email,
            'language'      => $lang,
            'roles'         => \Auth::READER,
            'created'       => \Time::now(),
            'created_by'    => \Auth::check() ? \Auth::user()->id : 0,       // to allow user fetch through API
            'modified'      => \Time::now(),
            'modified_by'   => 0,
        ));

        if (!$user->id)
        {
            throw new \Exception('Cannot add user');
        }

        // profile and role are created automatically, just fill in some stuff
        $profile = \Sys::svc('Profile')->findByUserId($user->id, true);

        $profile->firstname = $firstName;
        $profile->lastname  = $lastName;
        \Sys::svc('Profile')->update($profile);

        if ($login)
        {
            $_SESSION['-AUTH']['user'] = $user;
            $_SESSION['-AUTH']['profile'] = \Sys::svc('Profile')->findByUserId($user->id);
        }

        \Sys::svc('Resque')->addJob('UserAdd', array('user_id' => $user->id));

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

    /**
     * Generates a password restore link and sends it to the user
     *
     * @param $email
     * @param $callbackUrl
     * @throws \Exception
     */
    public function requestNewPassword($email, $callbackUrl)
    {
        if (!$user = \Sys::svc('User')->findByEmail($email))
        {
            throw new \Exception(\Lang::translate('User does not exist.'));
        }

        $id = $user->id;
        $ts = \Time::now();
        $requestKey = strtoupper(substr(sha1($id . '####' . $ts), 0, 15));

        $stmt = \DB::prepare('INSERT INTO user_password_reset VALUES (?,?,?)', [$requestKey, $id, $ts]);
        $stmt->execute();

        // notify user
        \Sys::svc('Resque')->addJob('PasswordRestore', array
        (
            'email'  => $email,
            'link'   => $callbackUrl . $requestKey,
        ));
    }

    /**
     * Resets a password for a key-validated user
     *
     * @param $key
     * @param $pass
     * @param $passVerify
     * @throws \Exception
     */
    public function restorePassword($key, $pass, $passVerify)
    {
        $data = \DB::row('SELECT * FROM user_password_reset WHERE request_key = ?', [$key]);

        $this->changePassword($data->user_id, $pass, $passVerify);

        // remove used key
        \DB::row('DELETE FROM user_password_reset WHERE request_key = ? AND user_id = ? LIMIT 1', [$key, $data->user_id]);
    }
}