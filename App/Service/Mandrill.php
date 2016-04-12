<?php

namespace App\Service;

class Mandrill
{
    protected $mandrill = null;
    
    public function __construct()
    {
        $config = \Sys::cfg(null);
        $this->mandrill = new \Mandrill($config['mandrill']['api_key']);
    }

    /**
     * Sends an email to one or more recipients
     *
     * @param $to
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
            'text'      => $body,
            'subject'   => $subject,
        );

        foreach ($to as $toAtom)
        {
            $setup['to'][] = array
            (
                'type'  => 'to',
                'email' => $toAtom['email'],
                'name'  => $toAtom['name'],
            );
        }
        
        return $this->mandrill->call('messages/send-template', $setup);
    }
}
