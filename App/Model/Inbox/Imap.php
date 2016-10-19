<?php

namespace App\Model\Inbox;
use App\Model\EmailParser;

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

        $this->userId = $user->id;
        $settings = json_decode($user->settings, true) ?: [];

        if (!$hash = @$settings['hash'])
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

        $this->connector = @imap_open('{' . $this->in['host'] . ':' . $this->in['port'] . '/imap/ssl/novalidate-cert/readonly}INBOX', $this->login, $this->password);

        if (!$this->connector)
        {
            // clear hash
            $settings['hash'] = null;
            $user->settings = json_encode($settings);
            \Sys::svc('User')->update($user);

            // ask to relogin
            \Sys::svc('Notify')->firebase(array
            (
                'to'           => '/topics/user-' . $user->id,
                'priority'     => 'high',

                'notification' => array
                (
                    'title' => 'Did you change password?',
                    'body'  => 'We apologize, but please login once again.',
                    'icon'  => 'fcm_push_icon'
                ),

                'data' => array
                (
                    'authId'    => $user->id,
                    'cmd'       => 'logout',
                ),
            ));

            \Sys::svc('Notify')->im(['cmd' => 'sys', 'userIds' => [$user->id], 'message' => 'logout']);
        }
    }

    /**
     * @return bool
     */
    public function checkNew()
    {
        if (!$this->connector)
        {
            return false;
        }

        // TODO: adjust time
        $ids = $this->getMessages(['ts_after' => strtotime('yesterday')]);

        if (!count($ids))
        {
            return false;
        }

        $row = \DB::row('SELECT ext_id FROM message WHERE ref_id=? ORDER BY id DESC LIMIT 1', [$this->userId]);

        return !$row || $row->ext_id != $ids[0];
    }

    /**
     * @param array $options
     * @return array
     */
    public function getMessages($options = [])
    {
        $query = 'ALL';
        if (!$this->connector)
        {
            return [];
        }

        if (isset ($options['ts_after']))
        {
            $query = 'SINCE "' . date('Y-m-d', $options['ts_after']) . '"';
        }

        return array_reverse(imap_search($this->connector, $query, SE_UID));
    }

    /**
     * @param $messageId
     * @return array
     */
    public function getMessage($messageId)
    {
        if (!$this->connector)
        {
            return [];
        }

        if (!$overview = imap_fetchbody($this->connector, $messageId, '', FT_UID))
        {
            return [];
        }

        $email = new EmailParser($overview);
        $headers = $email->getHeaders();

        return array
        (
            'message_id' => $messageId,
            'subject'    => $email->getSubject(),
            'addresses'  => $this->getAddresses($headers),
            'body'       => $email->getBodies(),
            'headers'    => $headers,
            'files'      => $email->getAttachments(),
            'date'       => strtotime($headers['date'][0]),
            'folders'    => [],     // not really clear ow to fill in this for a standard IMAP
        );
    }

    /**
     * @param $messageId
     * @param $fileId
     * @return mixed
     */
    public function getFileData($messageId, $fileId)
    {
        $data = $this->getMessage($messageId);

        return @$data['files'][$fileId]['content'];
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
