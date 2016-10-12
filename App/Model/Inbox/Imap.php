<?php

namespace App\Model\Inbox;

/**
 * Class Imap
 * @package App\Model
 */
class Imap extends Generic implements InboxInterface
{
    private $login = null;
    private $password = null;
    private $in = [];
    private $imapRef = '';

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

    public function checkNew($userId)
    {
        if (!$box = $this->imap_open())
        {
            throw new \Exception('Incorrect username or password.');
        }

        imap_close($box);
    }

    public function getMessages()
    {
        if (!$box = $this->imap_open())
        {
            throw new \Exception('Incorrect username or password.');
        }

        $MC = imap_check($box);

        $messages = imap_fetch_overview($box, "1:{$MC->Nmsgs}");

        foreach ($messages as $message)
        {
            print_r($message);
            /*$folder = str_replace($this->imapRef, '', imap_base64($folder));
            echo "Folder = $folder\n";*/
        }

        imap_close($box);
    }

    public function getMessage($messageId)
    {
    }

    /**
     * @return resource
     * @throws \Exception
     */
    private function imap_open()
    {
        if (!$this->in)
        {
            throw new \Exception('Mail service not configured');
        }

        $this->imapRef = '{' . $this->in['host'] . ':' . $this->in['port'] . '/imap/ssl/novalidate-cert/readonly}';

        return imap_open($this->imapRef, $this->login, $this->password);
    }
}
