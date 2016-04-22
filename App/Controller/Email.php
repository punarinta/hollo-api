<?php

namespace App\Controller;

/**
 * Class Email
 * @package App\Controller
 * @doc-api-path /api/email
 */
class Email extends Generic
{
    /**
     * Starts OAuth process
     *
     * @return bool
     * @throws \Exception
     */
    static function oAuthStart()
    {
        return \Sys::svc('Mailbox')->getOAuthToken();
    }

    /**
     * Completes OAuth process
     *
     * @return mixed
     * @throws \Exception
     */
    static function oAuthComplete()
    {
        if (!$code = \Input::data('code'))
        {
            throw new \Exception('No code provided.');
        }

        \Sys::svc('Mailbox')->processOAuthCode($code);
        
        return true;
    }

    /**
     * Discovers IMAP settings for a specified email
     *
     * @return mixed
     * @throws \Exception
     */
    static function discover()
    {
        if (!$email = \Input::data('email'))
        {
            throw new \Exception(\Lang::translate('No email provided.'));
        }

        $discover = \Sys::svc('Mailbox')->discoverEmail($email);

        if ($discover['oauth'])
        {
            $discover['url'] = \Sys::svc('Mailbox')->getOAuthToken($email, \Auth::profile()->first_name, \Auth::profile()->last_name);
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
    static function attach()
    {
        if (!$email = \Input::data('email'))
        {
            throw new \Exception(\Lang::translate('No email provided.'));
        }

        return \Sys::svc('Mailbox')->attachEmail($email, \Input::data('password'), \Input::data('server'), \Input::data('port'), \Input::data('username'));
    }
}
