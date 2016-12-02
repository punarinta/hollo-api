<?php

namespace App\Service;
use App\Model\Inbox\Inbox;
use MongoDB\BSON\ObjectID;

require_once 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php';
require_once 'vendor/guzzlehttp/promises/src/functions_include.php';
require_once 'vendor/guzzlehttp/psr7/src/functions_include.php';
require_once 'vendor/guzzlehttp/guzzle/src/functions_include.php';

class Smtp
{
    protected $mail = null;
    protected $setup = false;
    protected $messageExisted = false;

    public function __construct()
    {
        $this->mail = new \PHPMailer();
    }

    /**
     * Sets up message sending for a particular thread
     *
     * @param $user
     * @param null $chat
     * @param null $tempMsgId
     * @return bool
     * @throws \Exception
     */
    public function setupThread($user, $chat = null, $tempMsgId = null)
    {
        if (!is_object($user))
        {
            $user = \Sys::svc('User')->findOne(['_id' => new ObjectID($user)]);
        }

        if (!$user) return false;

        $out = \Sys::svc('MailService')->getCfg($user->settings->svc, 'out');

        if ($out->oauth)
        {
            $this->mail = new \PHPMailerOAuth();
            $this->mail->AuthType = 'XOAUTH2';
            $this->mail->oauthUserEmail = $user->email;
            $this->mail->oauthClientId = \Sys::cfg('oauth.google.clientId');
            $this->mail->oauthClientSecret = \Sys::cfg('oauth.google.secret');
            $this->mail->oauthRefreshToken = $_SESSION['-AUTH']['mail']['token'];
        }
        else
        {
            $this->mail = new \PHPMailer();
            $this->mail->Username = $_SESSION['-AUTH']['mail']['user'];
            $this->mail->Password = $_SESSION['-AUTH']['mail']['pass'];
        }

        // TODO: support overridden host/port
        $this->mail->Host = $out->host;
        $this->mail->Port = $out->port;

        $this->mail->SMTPSecure = $out->enc;
        $this->mail->SMTPAuth = true;
    //    $this->mail->SMTPDebug = 4;
        $this->mail->isSMTP();
        $this->mail->CharSet = 'UTF-8';

        $this->mail->Subject = '';

        if ($tempMsgId)
        {
            $this->mail->addCustomHeader('X-Temporary-ID: ' . $tempMsgId);
        }
        
        if ($chat)
        {
            // get external message ID
            if (!$message = \Sys::svc('Message')->findByLastRealByChat($chat))
            {
                throw new \Exception('Message does not exist');
            }

            $refUser = \Sys::svc('User')->findOne(['_id' => new ObjectID($message->refId)]);

            // temporary messages do not have external IDs and external Owner IDs
            if ($message->extId && $refUser->roles)
            {
                // get original message data

                $inbox = Inbox::init($refUser);

                if ($data = $inbox->getMessage($message->extId))
                {
                    $emailMessageId = $data['headers']['message-id'][0];

                    $refs = isset ($data['headers']['references']) ? $data['headers']['references'] : [];
                    array_push($refs, $emailMessageId);

                    $this->mail->addCustomHeader('Message-ID: ' . \Text::GUID_v4() . '@' . \Sys::cfg('mailless.this_server'));
                    $this->mail->addCustomHeader('In-Reply-To: ' . $emailMessageId);
                    $this->mail->addCustomHeader('References: ' . implode(' ', $refs));

                    $this->mail->Subject = 'Re: ' . $data['subject'];

                    if (isset ($data['body']))
                    {
                        $this->messageExisted = true;

                        $content = mb_convert_encoding($data['body'][0]['content'], 'UTF-8');

                        $body = [];
                        foreach (preg_split("/\r\n|\n|\r/", $content) as $line)
                        {
                            $body[] = '> ' . $line;
                        }

                        $ts = date('r', $data['date']);
                        $name = explode('@', $data['addresses']['from']['email']);
                        $this->mail->Body = "\nOn {$ts}, {$name[0]} <{$data['addresses']['from']['email']}> wrote:\n\n" . implode("\n", $body);
                    }
                }
            }
        }

        $name = \Sys::svc('User')->name($user);
        $this->mail->setFrom($user->email, $name);
        $this->mail->addReplyTo($user->email, $name);

        $this->setup = true;

        return true;
    }

    /**
     * Sends a message, can add more recipients
     *
     * @param $chat
     * @param $body
     * @param null $subject
     * @param array $attachments
     * @return array
     * @throws \Exception
     */
    public function send($chat, $body, $subject = null, $attachments = [])
    {
        $tempFiles = [];

        if (!$this->setup)
        {
            throw new \Exception('Sending was not setup');
        }

        if ($body)
        {
            // body may be empty in case of a files only post
            $this->mail->Body = $body . "\n" . $this->mail->Body;
        }

        if ($subject)
        {
            if (!$this->messageExisted || \Sys::svc('Message')->clearSubject($this->mail->Subject) != $subject)
            {
                // this means the mail did not exist or message existed, but this one is new
                $this->mail->Body = $body;
                $this->mail->Subject = $subject;
            }
        }
        else
        {
            // no subject -> do not quote
            $this->mail->Body = $body;
        }

        $userIds = [];

        foreach ($chat->users ?? [] as $userRow)
        {
            $user = \Sys::svc('User')->findOne(['_id' => new ObjectID($userRow->id)]);
            $this->mail->addAddress($user->email, @$user->name);

            // collect user IDs for IM notification
            $userIds[] = $user->_id;

            \Sys::svc('Notify')->firebase(array
            (
                'to'           => '/topics/user-' . $userRow->id,
                'priority'     => 'high',

                'notification' => array
                (
                    'title' => $subject,
                    'body'  => $body,
                    'icon'  => 'fcm_push_icon'
                ),

                'data' => array
                (
                    'authId' => $userRow->id,
                    'cmd'    => 'show-chat',
                    'chatId' => $chat->_id,
                ),
            ));
        }

        \Sys::svc('Notify')->im(['cmd' => 'notify', 'userIds' => $userIds, 'chatId' => $chat->_id]);

        foreach ($attachments as $file)
        {
            // support new and old field names
            // TODO: remove after new frontend released
            if (isset ($file['data']) && !isset ($file['b64']))
            {
                $file['b64'] = $file['data'];
            }

            // save file first
            $path = tempnam('data/temp', 'upl-');

            $f = fopen($path, 'wb');
            stream_filter_append($f, 'convert.base64-decode');
            fwrite($f, substr($file['b64'], strpos($file['b64'], ',') + 1));
            fclose($f);

            $tempFiles[] = $path;

            $this->mail->addAttachment($path, $file['name'], 'base64', $file['type']);
        }

        if (!$this->mail->send())
        {
            throw new \Exception($this->mail->ErrorInfo);
        }

        // cleanup possible temporary files
        foreach ($tempFiles as $file)
        {
            unlink($file);
        }

        return [$this->mail, $this->mail->getCustomHeaders(), $this->mail->getToAddresses(), $this->mail->getCcAddresses(), $this->mail->getAttachments()];
    }
}
