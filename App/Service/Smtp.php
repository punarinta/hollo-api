<?php

namespace App\Service;
use App\Model\ContextIO\ContextIO;

include_once 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php';
include_once 'vendor/guzzlehttp/promises/src/functions_include.php';
include_once 'vendor/guzzlehttp/psr7/src/functions_include.php';
include_once 'vendor/guzzlehttp/guzzle/src/functions_include.php';

class Smtp
{
    protected $conn = null;
    protected $mail = null;
    protected $setup = false;

    public function __construct()
    {
        $cfg = \Sys::cfg('contextio');
        $this->conn = new ContextIO($cfg['key'], $cfg['secret']);
    }

    public function setupThread($userId, $messageId = null)
    {
        if (!$user = \Sys::svc('User')->findById($userId))
        {
            return false;
        }

        if (!count($mailboxes = \Sys::svc('Mailbox')->findByUserId($userId)))
        {
            throw new \Exception('No mailboxes attached.');
        }

        $mailbox = $mailboxes[0];

        $settings = json_decode($mailbox->settings, true) ?:[];

        if (!file_exists($fileName = 'mail-services/' . $settings['service'] . '.json'))
        {
            throw new \Exception('Unsupported mail service');
        }

        $smtpConfig = \Sys::aPath(json_decode(file_get_contents($fileName), true), 'out');

        if ($smtpConfig['oauth'])
        {
            $this->mail = new \PHPMailerOAuth();
            $this->mail->AuthType = 'XOAUTH2';
            $this->mail->oauthUserEmail = $mailbox->email;
            $this->mail->oauthClientId = \Sys::cfg('social_auth.google.clientId');
            $this->mail->oauthClientSecret = \Sys::cfg('social_auth.google.secret');
            $this->mail->oauthRefreshToken = $settings['token'];
        }
        else
        {
            $this->mail = new \PHPMailer();
            $this->mail->Username = $settings['user'];
            $this->mail->Password = $settings['pass'];
        }

        // TODO: support overridden host/port
        $this->mail->Host = $smtpConfig['host'];
        $this->mail->Port = $smtpConfig['port'];

        $this->mail->SMTPSecure = $smtpConfig['enc'];
        $this->mail->SMTPAuth = true;
        $this->mail->SMTPDebug = 4;
        $this->mail->isSMTP();
        
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
                        $this->mail->addCC($from['email'], $from['name']);
                    }
                }
            }
        }

        // TODO: change user.email onto profile.name
        $this->mail->setFrom($mailbox->email, $user->email);
        $this->mail->addReplyTo($mailbox->email, $user->email);

        $this->setup = true;

        return true;
    }

    /**
     * @param array $to
     * @param $body
     * @param null $subject
     * @return bool
     * @throws \Exception
     * @throws \phpmailerException
     */
    public function send($to = [], $body, $subject = null)
    {
        if (!$this->setup)
        {
            throw new \Exception('Send not setup');
        }

        $this->mail->Body = $body . "\n" . $this->mail->Body;

        if ($to && $subject)
        {
            $this->mail->Subject = $subject;
        }

        foreach ($to as $toAtom)
        {
            $this->mail->addAddress($toAtom['email'], $toAtom['name']);
        }

        require 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php';

        if (!$this->mail->send())
        {
            throw new \Exception($this->mail->ErrorInfo);
        }

        return true;
    }
}
