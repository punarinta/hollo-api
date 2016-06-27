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
                'email'     => \Auth::user()->email,
                'name'      => \Sys::svc('User')->name(),
                'ava'       => @$_SESSION['-AUTH']['avatar'],
                'settings'  => json_decode(\Auth::user()->settings),
            ) : null,
            'sessionId' => session_id(),
        );
    }

    /**
     * Login via standard mechanisms
     *
     * @doc-var    (string) identity!       - User email.
     * @doc-var    (string) credential!     - User password.
     *
     * @return array
     * @throws \Exception
     */
    static function loginImap()
    {
        if (\Auth::check())
        {
            return self::status();
        }

        if (!$email = trim(\Input::data('identity')))
        {
            throw new \Exception(\Lang::translate('No email was provided.'));
        }

        if (!$password = trim(\Input::data('credential')))
        {
            throw new \Exception(\Lang::translate('No password was provided.'));
        }

        if ($user = \Sys::svc('User')->findByEmail($email))
        {
            // login
            \Sys::svc('Auth')->loginImap($user, $password);
        }
        else
        {
            // register
            $locale = \Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
            {
                throw new \Exception(\Lang::translate('Malformed email.'));
            }

            \Sys::svc('Auth')->registerImap($email, $password, $locale);
        }

        return self::status();
    }

    /**
     * Initializes OAuth authentication procedure
     *
     * @doc-var    (string) redirectUrl!     - Redirect URL.
     *
     * @return string
     */
    static function getOAuthToken()
    {
        // TODO: add a switch to support different OAuth providers

        return \Sys::svc('Auth')->getOAuthToken(null, \Input::data('redirectUrl'));
    }

    /**
     * Completes OAuth authentication procedure
     *
     * @doc-var    (string) redirectUrl!     - Redirect URL.
     *
     * @return array
     * @throws \Exception
     */
    static function processOAuthCode()
    {
        if (!$code = \Input::data('code'))
        {
            throw new \Exception(\Lang::translate('No code was provided.'));
        }

        \Sys::svc('Auth')->processOAuthCode($code, \Input::data('redirectUrl'));

        return self::status();
    }

    static function incarnate()
    {
        if (!\Auth::amI(\Auth::ADMIN))
        {
            throw new \Exception('Access denied.', 403);
        }

        if (!$userId = \Input::data('userId'))
        {
            throw new \Exception('No user ID provided.');
        }

        \Sys::svc('Auth')->incarnate($userId);

        return self::status();
    }

    static function wakeUp()
    {
        \Sys::svc('Auth')->wakeUp();
        return self::status();
    }
}