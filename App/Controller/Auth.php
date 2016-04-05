<?php

namespace App\Controller;

/**
 * Class Auth
 * @package App\Controller
 * @doc-api-path /api/auth
 */
class Auth extends Generic
{
    /**
     * Logs you in
     *
     * @doc-var    (string) identity!   - User email.
     * @doc-var    (string) credential! - User password.
     * @doc-var    (int) lifetime       - Session lifetime, in minutes. Maximum — 1440.
     *
     * @return array
     * @throws \Exception
     */
    static function login()
    {
        if (\Auth::check())
        {
            return self::status();
        }

        if (!\Input::data('identity'))
        {
            throw new \Exception(\Lang::translate('No username was provided.'));
        }

        if (!\Input::data('credential'))
        {
            throw new \Exception(\Lang::translate('No password was provided.'));
        }

        \Sys::svc('Auth')->login(trim(\Input::data('identity')), trim(\Input::data('credential')));

        if ($lifetime = \Input::data('lifetime'))
        {
            session_cache_expire(min($lifetime, 180));
        }

        return self::status();
    }

    /**
     * Logs you in using Google Authentication
     *
     * @doc-var    (string) idToken!    - A token from JS login widget.
     * @doc-var    (int) userId         - Pass User ID to initiate provider change procedure.
     *
     * @return mixed
     * @throws \Exception
     */
    static function loginGoogle()
    {
        if (!$idToken = \Input::data('idToken'))
        {
            throw new \Exception('Token is not provided.');
        }

        $ch = curl_init('https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . $idToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($result, true) ?:[];

        if (isset ($json['email']))
        {
            $user = \Sys::svc('User')->findByEmail($json['email']);

            if (!$user)
            {
                // if no user exist, create
                $user = \Sys::svc('User')->create(array
                (
                    'email'     => $json['email'],
                    'created'   => \Time::now(),
                    'provider'  => 'google',
                    'roles'     => \Auth::USER,
                ));
            }
            else
            {
                // validate the provider
                if ($user->provider != 'google')
                {
                    throw new \Exception(\Lang::translate('You do not use Google authentication.'));
                }
            }

            // login with userId
            \Sys::svc('Auth')->incarnate($user->id, true, true);
        }

        return self::status();
    }

     /**
     * Logs you out
     */
    static function logout()
    {
        \Sys::svc('Auth')->logout();
    }

    /**
     * Returns current status
     *
     * @return array
     */
    static function status()
    {
        return array
        (
            'user'      => \Auth::check() ? array
            (
                'id'        => \Auth::user()->id,
                'contextId' => \Auth::user()->context_id,
                'locale'    => \Auth::user()->locale,
                'email'     => \Auth::user()->email,
            ) : null,
            'roles'     => \Auth::textRoles(isset ($_SESSION['-AUTH']['user']) ? $_SESSION['-AUTH']['user']->roles : 0),
            'sessionId' => session_id(),
        );
    }

    /**
     * Gets an access token
     *
     * @doc-var    (string) email!          - Your email.
     * @doc-var    (string) firstName       - First name.
     * @doc-var    (string) lastName        - Last name.
     *
     * @return bool
     * @throws \Exception
     */
    static function getOAuthToken()
    {
        if (!$email = trim(\Input::data('email')))
        {
            throw new \Exception(\Lang::translate('No email provided.'));
        }

        // check that the user does not exist
        if (\Sys::svc('User')->findByEmail($email))
        {
            throw new \Exception(\Lang::translate('Cannot register. Probably this email address is already taken.'));
        }

        return \Sys::svc('Auth')->getOAuthToken($email, \Input::data('firstName'), \Input::data('lastName'));
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    static function saveContextIdByToken()
    {
        if (!$token = \Input::data('token'))
        {
            throw new \Exception('No token provided.');
        }
        
        return \Sys::svc('Auth')->saveContextIdByToken($token);
    }

    /**
     * Register a user in the system.
     *
     * @doc-var    (string) email!          - Email.
     * @doc-var    (string) password!       - Password.
     * @doc-var    (string) firstName       - First name.
     * @doc-var    (string) lastName        - Last name.
     *
     * @return bool
     * @throws \Exception
     */
    static function register()
    {
        if (!$email = trim(\Input::data('email')))
        {
            throw new \Exception(\Lang::translate('No email provided.'));
        }
        if (!$password = \Input::data('password'))
        {
            throw new \Exception(\Lang::translate('No password provided.'));
        }

        // check that the user does not exist
        if (\Sys::svc('User')->findByEmail($email))
        {
            throw new \Exception(\Lang::translate('Cannot register. Probably this email address is already taken.'));
        }

        \Sys::svc('Auth')->register($email, $password, \Input::data('firstName'), \Input::data('lastName'), 'en_US', true);

        return self::status();
    }

    /**
     * Discovers IMAP settings for a specified email
     *
     * @return mixed
     * @throws \Exception
     */
    static function discoverEmail()
    {
        if (!$email = \Input::data('email'))
        {
            throw new \Exception(\Lang::translate('No email provided.'));
        }
        
        $discover = \Sys::svc('Auth')->discoverEmail($email);
        
        if ($discover['oauth'])
        {
            $discover['url'] = \Sys::svc('Auth')->getOAuthToken($email, \Auth::profile()->first_name, \Auth::profile()->last_name);
        }

        return $discover;
    }

    /**
     * Attaches email and creates an account if necessary
     * 
     * @doc-var    (string) email!          - Email.
     * @doc-var    (string) password        - Password.
     * @doc-var    (string) server          - Server.
     * @doc-var    (string) port            - Port.
     * @doc-var    (string) username        - Username.
     *
     * @return mixed
     * @throws \Exception
     */
    static function attachEmail()
    {
        if (!$email = \Input::data('email'))
        {
            throw new \Exception(\Lang::translate('No email provided.'));
        }
        
        return \Sys::svc('Auth')->attachEmail($email, \Input::data('password'), \Input::data('server'), \Input::data('port'), \Input::data('username'));
    }

    static function incarnate()
    {
        if (!\Auth::amI(\Auth::ADMIN))
        {
            throw new \Exception(\Lang::translate('Access denied.'), 403);
        }

        if (!\Input::data('userId'))
        {
            throw new \Exception(\Lang::translate('No user ID provided.'));
        }

        \Sys::svc('Auth')->incarnate(\Input::data('userId'));

        return self::status();
    }

    static function wakeUp()
    {
        \Sys::svc('Auth')->wakeUp();
        return self::status();
    }
}