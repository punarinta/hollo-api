<?php

namespace App\Model\Inbox;
use App\Model\Plancake;

/**
 * Class Imap
 * @package App\Model
 */
class Imap extends Generic implements InboxInterface
{
    private $in = [];
    private $login = null;
    private $password = null;
    private $connector = null;

    /**
     * Init and get a password
     *
     * Google constructor.
     * @param $user
     */
    public function __construct($user)
    {
        if (!is_object($user))
        {
            $user = \Sys::svc('User')->findById($user);
        }

        $settings = json_decode($user->settings, true) ?: [];

        if (!$hash = $settings['hash'])
        {
            return;
        }

        $key = \Sys::cfg('sys.imap_hash');

        $this->login = $user->email;
        $this->password = openssl_decrypt($hash, 'aes-256-cbc', $key, 0, $key);

        if (!$this->in = \Sys::svc('MailService')->getCfg($settings['svc']))
        {
            return;
        }
    }

    /**
     * @param $userId
     */
    public function checkNew($userId)
    {
        $this->checkOpened();
    }

    /**
     * @param array $options
     * @return array
     */
    public function getMessages($options = [])
    {
        $query = 'ALL';
        $this->checkOpened();

        if (isset ($options['ts_after']))
        {
            $query = 'SINCE "' . date('Y-m-d', $options['ts_after']) . '"';
        }

        return imap_search($this->connector, $query, SE_UID);
    }

    /**
     * @param $messageId
     * @return array
     */
    public function getMessage($messageId)
    {
        $this->checkOpened();

        if (!$overview = imap_fetchbody($this->connector, $messageId, '', FT_UID))
        {
            return [];
        }

        $email = new Plancake($overview);
        $headers = $email->getHeaders();

        return array
        (
            'message_id' => $messageId,
            'subject'    => $email->getSubject(),
            'addresses'  => $this->getAddresses($headers),
            'body'       => '',
            'headers'    => $headers,
            'files'      => '',
            'date'       => strtotime($headers['date']),
            'folders'    => '',
        );
    }

    /**
     * Check if the connection is opened and open if necessary
     *
     * @throws \Exception
     */
    private function checkOpened()
    {
        if (!$this->connector)
        {
            if (!$this->in)
            {
                throw new \Exception('Mail service not configured');
            }

            $this->connector = imap_open('{' . $this->in['host'] . ':' . $this->in['port'] . '/imap/ssl/novalidate-cert/readonly}', $this->login, $this->password);
        }
    }

    /**
     * Kill connector with fire on class destruction
     */
    public function __destruct()
    {
        if ($this->connector)
        {
            imap_close($this->connector);
        }
    }
}
