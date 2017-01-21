<?php

namespace App\Model\Inbox;

use MongoDB\BSON\ObjectID;
use App\Model\EmailParser;
use \App\Service\User as UserSvc;
use \App\Service\Notify as NotifySvc;
use \App\Service\Chat as ChatSvc;
use \App\Service\MailService as MailServiceSvc;

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
            $user = UserSvc::findOne(['_id' => new ObjectID($user)]);
        }

        if (!$user->settings->hash)
        {
            return;
        }

        $this->user = $user;

        $key = \Sys::cfg('sys.imap_hash');

        $this->login = $user->email;
        $this->password = openssl_decrypt($this->user->settings->hash, 'aes-256-cbc', $key, 0, $key);

        if (!$this->in = MailServiceSvc::getCfg($this->user->settings->svc))
        {
            return;
        }

        $this->connector = @imap_open('{' . $this->in->host . ':' . $this->in->port . '/imap/ssl/novalidate-cert/readonly}INBOX', $this->login, $this->password);

        if (!$this->connector)
        {
            // clear hash
            $user->settings->hash = null;
            UserSvc::update($user, ['settings.hash' => null]);

            // ask to relogin
            NotifySvc::auto([$user->_id], ['cmd' => 'auth:logout'], ['title' => 'Did you change password?', 'body' => 'We apologize, but please login once again.']);
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

        $latestTs = 0;
        $latestExtId = null;

        // find all chats where this user's messages are present
        foreach (ChatSvc::findAll(['messages.refId' => $this->user->_id]) as $chat)
        {
            foreach ($chat->messages as $message)
            {
                if ($message->ts > $latestTs)
                {
                    $latestTs = $message->ts;
                    $latestExtId = $message->extId;
                }
            }
        }

        return $latestExtId != $ids[0];
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
        // support feeding message data as a first argument
        if (is_array($messageId))
        {
            $data = $messageId;
        }
        else
        {
            $data = $this->getMessage($messageId);
        }

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
