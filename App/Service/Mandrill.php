<?php

namespace App\Service;
use App\Model\ContextIO\ContextIO;

class Mandrill
{
    protected $conn = null;
    protected $mandrill = null;
    protected $mandrillParams = [];

    public function __construct()
    {
        $this->mandrill = new \Mandrill(\Sys::cfg('mandrill.api_key'));

        $cfg = \Sys::cfg('contextio');
        $this->conn = new ContextIO($cfg['key'], $cfg['secret']);
    }

    /**
     * Configures threaded mail sending
     *
     * @param $userId
     * @param $messageId
     * @return bool
     */
    public function setupThread($userId, $messageId)
    {
        if (!$user = \Sys::svc('User')->findById($userId))
        {
            return false;
        }

        // get original message data
        $data = $this->conn->getMessage($user->id, ['message_id' => $messageId, 'include_body' => 1]);

        if (!$data)
        {
            return false;
        }

        $data = $data->getData();

        $refs = $data['references'];
        array_push($refs, $data['email_message_id']);

        $this->mandrillParams['subject'] = $data['subject'];
        $this->mandrillParams['headers'] = array
        (
            'Reply-To'    => $user->email,
            'Message-ID'  => \Text::GUID_v4() . '@hollo.email',
            'In-Reply-To' => $data['email_message_id'],
            'References'  => $refs,
        );
        $this->mandrillParams['subject'] = /*'Re: ' . */ $data['subject'];
        $this->mandrillParams['text'] = $data['body'][0]['content'];


        if (isset ($data['addresses']['from'])) foreach ($data['addresses']['from'] as $from)
        {
            $this->mandrillParams['to'][] = array
            (
                'type'  => 'to',
                'email' => $from['email'],
                'name'  => $from['name'],
            );
        }

        if (isset ($data['addresses']['cc'])) foreach ($data['addresses']['cc'] as $from)
        {
            $this->mandrillParams['to'][] = array
            (
                'type'  => 'cc',
                'email' => $from['email'],
                'name'  => $from['name'],
            );
        }

        if (isset ($data['addresses']['bcc'])) foreach ($data['addresses']['bcc'] as $from)
        {
            $this->mandrillParams['to'][] = array
            (
                'type'  => 'bcc',
                'email' => $from['email'],
                'name'  => $from['name'],
            );
        }

        return true;
    }

    /**
     * Sends an email to one or more recipients
     *
     * @param array $to — if setupThread() was called, 'to' must be an empty array
     * @param $body
     * @param string $subject
     * @return mixed
     * @throws \Exception
     * @throws \Mandrill_Error
     * @throws \Mandrill_HttpError
     */
    public function send($to, $body, $subject = '')
    {
        if (!$this->mandrill)
        {
            throw new \Exception('send(): no Mandrill connection');
        }

        $setup = array
        (
            'acync'     => true,
            'message'   => array
            (
                'text'      => $body,
                'subject'   => $subject,
            ),
        );
        
        if ($this->mandrillParams)
        {
            $setup['message'] = array_merge($setup['message'], $this->mandrillParams);
        }

        foreach ($to as $toAtom)
        {
            $setup['message']['to'][] = array
            (
                'type'  => 'to',
                'email' => $toAtom['email'],
                'name'  => $toAtom['name'],
            );
        }
        
        return $this->mandrill->call('messages/send-template', $setup);
    }
}
