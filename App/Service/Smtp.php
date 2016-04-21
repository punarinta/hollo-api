<?php

namespace App\Service;
use App\Model\ContextIO\ContextIO;

include_once 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php';

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
        $this->mail->isHTML(false);
        $this->mail->isSMTP();
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

        $this->mail->Host = $settings['host'];
        $this->mail->SMTPAuth = (bool) @(strlen($settings['pass']) > 0);
        $this->mail->Username = $settings['user'];
        $this->mail->Password = $settings['pass'];
        $this->mail->SMTPSecure = $settings['sec'];
        $this->mail->Port = $settings['port'];
        
        if ($messageId)
        {
            // get external message ID
            if (!$message = \Sys::svc('Message')->findById($messageId))
            {
                throw new \Exception('Message does not exist');
            }

            // get original message data
            $data = $this->conn->getMessage($user->ext_id, ['message_id' => $message->ext_id, 'include_body' => 1]);

            if (!$data)
            {
                return false;
            }

            $data = $data->getData();

            $refs = $data['references'];
            array_push($refs, $data['email_message_id']);

            $this->mail->addCustomHeader('Message-ID: ' . \Text::GUID_v4() . '@' . \Sys::cfg('mailless.this_server'));
            $this->mail->addCustomHeader('In-Reply-To: ' . $data['email_message_id']);
            $this->mail->addCustomHeader('References: ' . implode(' ', $refs));

            $this->mail->Subject = $data['subject'];
            $this->mail->Body = $data['body'][0]['content'];

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
        
        $this->mail->Body = $body;  // TODO: merge with the quoted version of an existing body

        if ($to && $subject)
        {
            $this->mail->Subject = $subject;
        }

        foreach ($to as $toAtom)
        {
            $this->mail->addAddress($toAtom['email'], $toAtom['name']);
        }

        if (!$this->mail->send())
        {
            throw new \Exception($this->mail->ErrorInfo);
        }

        return true;
    }
}
