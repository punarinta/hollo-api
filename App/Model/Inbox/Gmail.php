<?php

namespace App\Model\Inbox;

/**
 * Class Gmail
 * @package App\Model
 */
class Gmail extends Generic implements InboxInterface
{
    private $accessToken = null;

    /**
     * Init and get a fresh token
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

        if (!$token = $settings['token'])
        {
            return;
        }

        $client = new \Google_Client();
        $client->setClientId(\Sys::cfg('oauth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('oauth.google.secret'));

        // TODO: not really a good idea to refresh the token all the time
        $client->refreshToken($token);
        $accessToken = $client->getAccessToken();
        $this->accessToken = json_decode($accessToken, true);
    }

    /**
     * @param $userId
     * @return bool
     */
    public function checkNew($userId)
    {
        $res = $this->curl('messages?maxResults=1&fields=messages');

        if (!isset ($res['messages'][0]['id']))
        {
            // no new ones
            return false;
        }

        $row = \DB::row('SELECT ext_id FROM message WHERE ref_id=? ORDER BY id DESC LIMIT 1', [$userId]);

        return !$row || $row->ext_id != $res['messages'][0]['id'];
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        $ids = [];
        $nextPageToken = null;

        while (1)
        {
            $pageTokenStr = $nextPageToken ? "&pageToken=$nextPageToken" : '';

            $res = $this->curl("messages?maxResults=100$pageTokenStr&fields=messages,nextPageToken");

            foreach ($res['messages'] as $message)
            {
                $ids[] = $message['id'];
            }

            if (!isset ($res['nextPageToken']))
            {
                break;
            }

            $nextPageToken = $res['nextPageToken'];
        }

        return $ids;
    }

    /**
     * @param $messageId
     * @return array
     */
    public function getMessage($messageId)
    {
        $headers = [];
        $raw = $this->curl("messages/$messageId");
        $payload = $raw['payload'];

        // print_r($raw);

        foreach ($payload['headers'] as $header)
        {
            if (!isset ($headers[$header['name']]))
            {
                $headers[$header['name']] = [];
            }

            $headers[$header['name']][] = $header['value'];
        }

        $bodies = [];
        $files = [];

        if ($payload['body'] && $payload['body']['size'])
        {
            $bodies[] = array
            (
                'type'      => 'text/html',
                'content'   => $this->base64_decode($payload['body']['data']),
            );
        }

        // normalize parts
        if (isset ($payload['parts'])) foreach ($payload['parts'] as $part)
        {
            if ($part['mimeType'] == 'multipart/alternative')
            {
                // we need to go deeper
                foreach ($part['parts'] as $subPart)
                {
                    $data = null;

                    if (isset ($subPart['body']['data']))
                    {
                        $data = $subPart['body']['data'];
                    }
                    else
                    {
                        foreach ($subPart['body'] as $k => $v)
                        {
                            if ($k != 'size')
                            {
                                $data = $v;
                                break;
                            }
                        }
                    }

                    if ($data) $bodies[] = array
                    (
                        'type'      => $subPart['mimeType'],
                        'content'   => $this->base64_decode($data),
                    );
                }
            }
            else
            {
                $files[] = array
                (
                    'type' => $part['mimeType'],
                    'name' => $part['filename'],
                //    'body' => $part['body'],
                );
            }
        }

        return array
        (
            'message_id'     => $messageId,
            'subject'        => $headers['Subject'][0],
            'addresses'      => $this->getAddresses($headers),
            'body'           => $bodies,
            'headers'        => $headers,
            'files'          => $files,
            'date'           => strtotime($headers['Date'][0]),
            'folders'       => $raw['labelIds'],
        );
    }

    /**
     * @param $url
     * @return array
     */
    private function curl($url)
    {
        $ch = curl_init('https://www.googleapis.com/gmail/v1/users/me/' . $url . (strpos($url, '?') === false ? '?' : '&') . 'oauth_token=' . $this->accessToken['access_token']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = json_decode(curl_exec($ch), true) ?:[];
        curl_close($ch);
        return $res;
    }
}
