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

        return \Sys::svc('Mailbox')->getOAuthToken($email, \Input::data('firstName'), \Input::data('lastName'));
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

        return \Sys::svc('Mailbox')->saveContextIdByToken($token);
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
