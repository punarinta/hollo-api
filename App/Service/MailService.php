<?php

namespace App\Service;

use App\Model\ContextIO\ContextIO;
use EmailAuth\Discover;

class MailService extends Generic
{
    /**
     * Returns configuration for a mail service
     *
     * @param $mailService      — both ID and object are supported
     * @param string $dir
     * @return array
     * @throws \Exception*
     */
    public function getCfg($mailService, $dir = 'in')
    {
        if (!is_object($mailService))
        {
            if (!$mailService = $this->findOne(['_id' => $mailService]))
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
    public function fullDiscoverAndSave($email)
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

        return $this->create(array
        (
            'name'      => $domain,
            'domains'   => "|$domain|",
            'cfgIn'     => json_encode(['type' => 'imap', 'oauth' => false, 'host' => $imapCfg['host'], 'port' => $imapCfg['port'], 'enc' => $imapCfg['encryption']]),
            'cfgOut'    => json_encode(['type' => 'smtp', 'oauth' => false, 'host' => $smtpCfg['host'], 'port' => $smtpCfg['port'], 'enc' => $smtpCfg['encryption']]),
        ));
    }

    /**
     * Finds a mail service by a mail domain
     *
     * @param $domain
     * @return null|\StdClass
     */
    public function findByDomain($domain)
    {
        return $this->findOne(['domains' => ['$elemMatch' => ['$eq' => $domain]]]);
    }

    /**
     * Finds a mail service by an associated email address
     *
     * @param $email
     * @return null|\StdClass
     * @throws \Exception
     */
    public function findByEmail($email)
    {
        $domain = explode('@', $email);

        if (count($domain) < 2)
        {
            throw new \Exception('Not an email address');
        }

        return $this->findByDomain($domain[1]);
    }

    /**
     * Discovers IMAP settings for a specified email. Returns 'cfgIn'.
     *
     * @param $email
     * @return array|bool
     */
    public function discoverEmail($email)
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