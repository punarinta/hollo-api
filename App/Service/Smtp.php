<?php

namespace App\Service;
use App\Model\ContextIO\ContextIO;

/*include_once 'vendor/phpmailer/phpmailer/class.phpmailer.php';
include_once 'vendor/phpmailer/phpmailer/class.phpmaileroauth.php';
include_once 'vendor/phpmailer/phpmailer/class.phpmaileroauthgoogle.php';*/
require_once 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php';
require_once 'vendor/guzzlehttp/promises/src/functions_include.php';
require_once 'vendor/guzzlehttp/psr7/src/functions_include.php';
require_once 'vendor/guzzlehttp/guzzle/src/functions_include.php';

class Smtp
{
    protected $conn = null;
    protected $mail = null;
    protected $setup = false;

    public function __construct()
    {
        $cfg = \Sys::cfg('contextio');
        $this->conn = new ContextIO($cfg['key'], $cfg['secret']);
        $this->mail = new \PHPMailer();
    }

    /**
     * Sets up message sending for a particular thread
     *
     * @param $userId
     * @param null $messageId
     * @return bool
     * @throws \Exception
     * @throws \phpmailerException
     */
    public function setupThread($userId, $messageId = null)
    {
        if (!$user = \Sys::svc('User')->findById($userId))
        {
            return false;
        }

        $out = \Sys::svc('MailService')->getCfg(\Sys::svc('User')->setting($user, 'svc'), 'out');

        if ($out['oauth'])
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
        $this->mail->Host = $out['host'];
        $this->mail->Port = $out['port'];

        $this->mail->SMTPSecure = $out['enc'];
        $this->mail->SMTPAuth = true;
    //    $this->mail->SMTPDebug = 4;
        $this->mail->isSMTP();

        $this->mail->Subject = '';
        
        if ($messageId)
        {
            // get external message ID
            if (!$message = \Sys::svc('Message')->findById($messageId))
            {
                throw new \Exception('Message does not exist');
            }

            // get original message data
            $data = $this->conn->getMessage($user->ext_id, ['message_id' => $message->ext_id, 'include_body' => 1]);

            if ($data)
            {
                $data = $data->getData();

                $refs = $data['references'];
                array_push($refs, $data['email_message_id']);

                $this->mail->addCustomHeader('Message-ID: ' . \Text::GUID_v4() . '@' . \Sys::cfg('mailless.this_server'));
                $this->mail->addCustomHeader('In-Reply-To: ' . $data['email_message_id']);
                $this->mail->addCustomHeader('References: ' . implode(' ', $refs));

                $this->mail->Subject = $data['subject'];

                $body = [];
                foreach (preg_split("/\r\n|\n|\r/", $data['body'][0]['content']) as $line)
                {
                    $body[] = '> ' . $line;
                }

                $ts = date('r', $data['date']);
                $name = explode('@', $data['addresses']['from']['name']);
                $this->mail->Body = "\nOn {$ts}, {$name[0]} <{$data['addresses']['from']['email']}> wrote:\n\n" . implode("\n", $body);

                if (isset ($data['addresses']['from']))
                {
                    // reply back to sender
                    $this->mail->addAddress($data['addresses']['from']['email'], $data['addresses']['from']['name']);
                }

                if (isset ($data['addresses']['cc']))
                {
                    foreach ($data['addresses']['cc'] as $from)
                    {
                        $this->mail->addCC($from['email'], @$from['name']);
                    }
                }

                if (isset ($data['addresses']['to']))
                {
                    foreach ($data['addresses']['to'] as $from)
                    {
                        if ($from['email'] != $user->email)
                        {
                            $this->mail->addCC($from['email'], @$from['name']);
                        }
                    }
                }
            }
        }

        $name = \Sys::svc('User')->name();
        $this->mail->setFrom($user->email, $name);
        $this->mail->addReplyTo($user->email, $name);

        $this->setup = true;

        return true;
    }

    /**
     * Sends a message, can add more recipients
     *
     * @param array $to
     * @param $body
     * @param null $subject
     * @param array $attachments
     * @return bool|null|\PHPMailer
     * @throws \Exception
     * @throws \phpmailerException
     */
    public function send($to = [], $body, $subject = null, $attachments = [])
    {
        $tempFiles = [];

        if (!$this->setup)
        {
            throw new \Exception('Send not setup');
        }

        $this->mail->Body = $body . "\n" . $this->mail->Body;

        if ($subject)
        {
            $this->mail->Subject = $subject;
        }

        foreach ($to as $toAtom)
        {
            $this->mail->addAddress($toAtom['email'], @$toAtom['name']);
        }

        foreach ($attachments as $file)
        {
            // save file first
            $path = tempnam('data/temp', 'upl-');
            $tempFiles[] = $path;

            $this->mail->addAttachment($path, $file['name'], 'base64', $file['type']);
        }

       /* if (!$this->mail->send())
        {
            throw new \Exception($this->mail->ErrorInfo);
        }*/

        // cleanup possible temporary files
        foreach ($tempFiles as $file)
        {
            unlink($file);
        }

        return [$this->mail, $this->mail->getToAddresses(), $this->mail->getCcAddresses(), $this->mail->getAttachments()];
    }
}
