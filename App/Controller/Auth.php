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
                'contextId' => \Auth::user()->context_id,
                'locale'    => \Auth::user()->locale,
                'email'     => \Auth::user()->email,
            ) : null,
            'roles'     => \Auth::textRoles(isset ($_SESSION['-AUTH']['user']) ? $_SESSION['-AUTH']['user']->roles : 0),
            'sessionId' => session_id(),
        );
    }

    /**
     * Smartly choose between login and register
     *
     * @doc-var    (string) identity!       - User email.
     * @doc-var    (string) credential!     - User password.
     *
     * @return array
     * @throws \Exception
     */
    static function logister()
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

        if (\Sys::svc('User')->findByEmail($email))
        {
            // login
            \Sys::svc('Auth')->login($email, $password);
        }
        else
        {
            // register
            // TODO: $locale = \Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            \Sys::svc('Auth')->register($email, $password, '', '', 'en_US', true);
        }

        return self::status();
    }

    /**
     * @return array
     * @throws \Exception
     */
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

    /**
     * @return array
     */
    static function wakeUp()
    {
        \Sys::svc('Auth')->wakeUp();
        return self::status();
    }
}