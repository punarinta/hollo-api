<?php

namespace App\Service;

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
            if (!$mailService = $this->findById($mailService))
            {
                throw new \Exception('Mail service does not exist');
            }
        }

        if ($dir == 'in')
        {
            return json_decode($mailService->cfg_in, true) ?:[];
        }
        else
        {
            return json_decode($mailService->cfg_out, true) ?:[];
        }
    }

    /**
     * Finds a mail service by a mail domain
     *
     * @param $domain
     * @return null|\StdClass
     */
    public function findByDomain($domain)
    {
        return \DB::row('SELECT * FROM mail_service WHERE domains LIKE ? LIMIT 1', ['%|' . $domain . '|%']);
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
}