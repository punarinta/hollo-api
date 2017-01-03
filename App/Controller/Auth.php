<?php

namespace App\Controller;

use \App\Service\Auth as AuthSvc;
use \App\Service\User as UserSvc;

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
        AuthSvc::logout();
    }

    /**
     * Returns current status
     *
     * @return array
     */
    static function status()
    {
        if (\Auth::check())
        {
            if (!isset (\Auth::user()->_id) || !\Auth::user()->_id)
            {
                AuthSvc::logout();
            }
        }

        return array
        (
            'user'      => \Auth::check() ? array
            (
                'id'        => \Auth::user()->_id,
                'email'     => \Auth::user()->email,
                'name'      => @\Auth::user()->name,
                'ava'       => @$_SESSION['-AUTH']['avatar'],
                'settings'  => \Auth::user()->settings,
                'created'   => \Auth::user()->created ?? 0,
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

        if (($user = UserSvc::findByEmail($email, true)) && $user->settings->svc)
        {
            // login
            AuthSvc::loginImap($user, $password);
        }
        else
        {
            // register
            $locale = \Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
            {
                throw new \Exception(\Lang::translate('Malformed email.'));
            }

            AuthSvc::registerImap($email, $password, $locale);
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

        return AuthSvc::getOAuthToken(null, \Input::data('redirectUrl'));
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

        AuthSvc::processOAuthCode($code, \Input::data('redirectUrl'));

        return self::status();
    }

    /**
     * Complete native Goggle auth
     *
     * @return array
     * @throws \Exception
     */
    static function processGoogleToken()
    {
        if (!$token = \Input::data('token'))
        {
            throw new \Exception(\Lang::translate('No token was provided.'));
        }

        AuthSvc::processOAuthCode($token['serverAuthCode']);

        return self::status();
    }

    static function incarnate()
    {
        if (!\Auth::amI(\Auth::ADMIN))
        {
            throw new \Exception('Method \'incarnate\' does not exist.', 404);
        }

        if (!$userId = \Input::data('userId'))
        {
            throw new \Exception('No user ID provided.');
        }

        AuthSvc::incarnate($userId);

        return self::status();
    }

    static function wakeUp()
    {
        AuthSvc::wakeUp();
        return self::status();
    }
}