<?php

namespace App\Service;
use App\Model\ContextIO\ContextIO;

class SparkPost
{
    protected $conn = null;
    protected $postParams = [];

    public function __construct()
    {
        $cfg = \Sys::cfg('contextio');
        $this->conn = new ContextIO($cfg['key'], $cfg['secret']);
    }

    /**
     * Configures threaded mail sending
     *
     * @param $userId
     * @param null $messageId
     * @return bool
     * @throws \Exception
     */
    public function setupThread($userId, $messageId = null)
    {
        if (!$user = \Sys::svc('User')->findById($userId))
        {
            return false;
        }

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

            $this->postParams['content']['headers'] = array
            (
                'Message-ID'  => \Text::GUID_v4() . '@' . \Sys::cfg('mailless.this_server'),
                'In-Reply-To' => $data['email_message_id'],
                'References'  => ' ' . implode(' ', $refs),
            );

            $this->postParams['content']['from'] = $user->email;
            $this->postParams['content']['reply_to'] = $user->email;
            $this->postParams['content']['subject'] = /*'Re: ' . */ $data['subject'];
            $this->postParams['content']['text'] = $data['body'][0]['content'];


            if (isset ($data['addresses']['from']))
            {
                $this->postParams['recipients'][] = array
                (
                    'address'  => array
                    (
                        'email' => $data['addresses']['from']['email'],
                    ),
                    'substitution_data' => array
                    (
                        'name'  => $data['addresses']['from']['name'],
                    ),
                );
            }

            if (isset ($data['addresses']['cc']))
            {
                $ccs = [];

                foreach ($data['addresses']['cc'] as $from)
                {
                    $cc = '<' . $from['email'] . '>';
                    if (isset($from['name'])) $cc = '"' . $from['name'] . '" ' . $cc;
                    $ccs[] = $cc;

                    $this->postParams['recipients'][] = array
                    (
                        'address'  => array
                        (
                            'email'     => $from['email'],
                            'header_to' => $data['addresses']['from']['email'],
                        ),
                        'substitution_data' => array
                        (
                            'name'  => $from['name'],
                        ),
                    );
                }
                $this->postParams['content']['headers']['CC'] = implode(', ', $ccs);
            }
        }

        $this->postParams['content']['from'] = str_replace('@', '._.', $user->email) . '@hollo.email';

        return true;
    }

    /**
     * Sends an email to one or more recipients
     *
     * @param array $to — if setupThread() was called, 'to' may be an empty array
     * @param $body
     * @param string $subject
     * @return mixed
     * @throws \Exception
     */
    public function send($to, $body, $subject = '')
    {
        $setup = $this->postParams;
        $setup['content']['text'] = $body;  // TODO: merge with the quoted version of an existing body

        if ($to)
        {
            $setup['content']['subject'] = $subject;
        }

        foreach ($to as $toAtom)
        {
            $setup['recipients'][] = array
            (
                'address'  => array
                (
                    'email' => $toAtom['email'],
                ),
                'substitution_data' => array
                (
                    'name'  => $toAtom['name'],
                ),
            );
        }

    /*    print_r($setup);
        echo "======================================================\n";
        exit;*/

        $res = $this->curl($setup);

        if (isset ($res['errors']))
        {
            throw new \Exception(json_encode($res['errors']));
        }

        return $res;
    }


    protected function curl($data)
    {
        $ch = curl_init('https://api.sparkpost.com/api/v1/transmissions');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization:' . \Sys::cfg('sparkpost.api_key')]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true);
    }
}