<?php

namespace App\Service;

use App\Model\ContextIO\ContextIO;
use EmailAuth\Discover;
use MongoDB\BSON\ObjectID;

class MailService extends Generic
{
    protected static $class_name = 'mail_service';

    /**
     * Returns configuration for a mail service
     *
     * @param $mailService      — both ID and object are supported
     * @param string $dir
     * @return mixed
     * @throws \Exception*
     */
    public static function getCfg($mailService, $dir = 'in')
    {
        if (!is_object($mailService))
        {
            if (!$mailService = self::findOne(['_id' => new ObjectID($mailService)]))
            {
                throw new \Exception('Mail service does not exist');
            }
        }

        if ($dir == 'in')
        {
            return $mailService->cfgIn;
        }
        else
        {
            return $mailService->cfgOut;
        }
    }

    /**
     * Tries to discover IMAP and SMTP settings and creates a new MailService
     *
     * @param $email
     * @return null|\StdClass
     */
    public static function fullDiscoverAndSave($email)
    {
        $discover = new Discover;

        if (!$imapCfg = $discover->imap($email))
        {
            return null;
        }

        if (!$smtpCfg = $discover->smtp($email))
        {
            return null;
        }

        $domain = explode('@', $email);
        $domain = $domain[1];

        return self::create(
        [
            'name'      => $domain,
            'domains'   => [$domain],
            'cfgIn'     => (object) ['type' => 'imap', 'oauth' => 0, 'host' => $imapCfg['host'], 'port' => $imapCfg['port'], 'enc' => $imapCfg['encryption']],
            'cfgOut'    => (object) ['type' => 'smtp', 'oauth' => 0, 'host' => $smtpCfg['host'], 'port' => $smtpCfg['port'], 'enc' => $smtpCfg['encryption']],
        ]);
    }

    /**
     * Finds a mail service by a mail domain
     *
     * @param $domain
     * @return null|\StdClass
     */
    public static function findByDomain($domain)
    {
        return self::findOne(['domains' => ['$elemMatch' => ['$eq' => $domain]]]);
    }

    /**
     * Finds a mail service by an associated email address
     *
     * @param $email
     * @return null|\StdClass
     * @throws \Exception
     */
    public static function findByEmail($email)
    {
        $domain = explode('@', $email);

        if (count($domain) < 2)
        {
            throw new \Exception('Not an email address');
        }

        return self::findByDomain($domain[1]);
    }

    /**
     * Discovers IMAP settings for a specified email. Returns 'cfgIn'.
     *
     * @param $email
     * @return array|bool
     */
    public static function discoverEmail($email)
    {
        $cfg = \Sys::cfg('contextio');
        $conn = new ContextIO($cfg['key'], $cfg['secret']);

        // TODO: refactor

        $r = $conn->discovery(array
        (
            'source_type'   => 'IMAP',
            'email'         => $email,
        ));

        if ($r = $r->getData())
        {
            if ($r['found'])
            {
                $r = $r['imap'];

                return array
                (
                    'ssl'       => $r['use_ssl'],
                    'port'      => $r['port'],
                    'oauth'     => $r['oauth'],
                    'server'    => $r['server'],
                    'username'  => $r['username'],
                );
            }
            else
            {
                return ['oauth' => false];
            }
        }

        return false;
    }
}