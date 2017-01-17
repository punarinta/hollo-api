<?php

namespace App\Model\Inbox;

use MongoDB\BSON\ObjectID;
use \App\Service\User as UserSvc;
use \App\Service\Chat as ChatSvc;
use \App\Service\Notify as NotifySvc;

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
            $user = UserSvc::findOne(['_id' => new ObjectID($user)]);
        }

        if (!$user->settings->token)
        {
            return;
        }

        $this->user = $user;

        $client = new \Google_Client();
        $client->setClientId(\Sys::cfg('oauth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('oauth.google.secret'));

        try
        {
            $client->refreshToken($user->settings->token);
            $accessToken = $client->getAccessToken();
            $this->accessToken = json_decode($accessToken, true);
        }
        catch (\Exception $e)
        {
            if (strpos($e->getMessage(), '"invalid_grant"'))
            {
                // clear token
                $user->settings->token = null;
                UserSvc::update($user, ['settings.token' => null]);

                // ask to relogin
                NotifySvc::auto([$user->_id], ['cmd' => 'auth:logout'], ['title' => 'Did you change password?', 'body' => 'We apologize, but please login once again.']);
            }
        }
    }

    /**
     * @return bool
     */
    public function checkNew()
    {
        $res = $this->curl('messages?maxResults=1&fields=messages&labelIds=INBOX');

        $latestTs = 0;
        $latestExtId = null;

        // find all chats where this user's messages are present
        foreach (ChatSvc::findAll(['messages.refId' => @$this->user->_id]) as $chat)
        {
            foreach ($chat->messages ?? [] as $message)
            {
                if ($message->ts > $latestTs && $message->extId)
                {
                    $latestTs = $message->ts;
                    $latestExtId = $message->extId;
                }
            }
        }

        if (!isset ($res['messages'][0]['id']))
        {
            $res = $this->curl('messages?maxResults=1&fields=messages&labelIds=SENT');
            if (!isset ($res['messages'][0]['id']))
            {
                // no new ones
                return false;
            }

            return $latestExtId != $res['messages'][0]['id'];
        }

        return $latestExtId != $res['messages'][0]['id'];
    }

    /**
     * @param $newHistoryId
     * @param string $labelId
     * @return array
     */
    public function listHistory($newHistoryId, $labelId = 'INBOX')
    {
        // get previous history ID
        $historyId = $this->user->settings->historyId ?: $newHistoryId;

        $res = $this->curl('history?startHistoryId=' . $historyId . ($labelId ? '&labelId=INBOX' . $labelId : ''));

        // update if anyone else decides to use that var
        $this->user->settings->historyId = $newHistoryId;
        UserSvc::update($this->user, ['settings.historyId' => $newHistoryId]);

        return isset ($res['history']) ? $res['history'] : [];
    }

    /**
     * @param array $options
     * @return array
     */
    public function getMessages($options = [])
    {
        $ids = [];
        $nextPageToken = null;

        while (1)
        {
            $query = '';
            $pageTokenStr = $nextPageToken ? "&pageToken=$nextPageToken" : '';

            if (isset ($options['ts_after']))
            {
                $query = '&q=' . urlencode('after:' . date('Y/m/d', $options['ts_after']));
            }

            $res = $this->curl("messages?maxResults=100$pageTokenStr&labelIds=INBOX&fields=messages,nextPageToken$query");

            if (!isset ($res['messages']))
            {
                // no (more) messages -> stop
                break;
            }

            foreach ($res['messages'] as $message)
            {
                $ids[$message['id']] = $message['id'];
            }

            if (!isset ($res['nextPageToken']))
            {
                break;
            }

            $nextPageToken = $res['nextPageToken'];
            usleep(100000);
        }

        while (1)
        {
            $query = '';
            $pageTokenStr = $nextPageToken ? "&pageToken=$nextPageToken" : '';

            if (isset ($options['ts_after']))
            {
                $query = '&q=' . urlencode('after:' . date('Y/m/d', $options['ts_after']));
            }

            $res = $this->curl("messages?maxResults=100$pageTokenStr&labelIds=SENT&fields=messages,nextPageToken$query");

            if (!isset ($res['messages']))
            {
                // no (more) messages -> stop
                break;
            }

            foreach ($res['messages'] as $message)
            {
                $ids[$message['id']] = $message['id'];
            }

            if (!isset ($res['nextPageToken']))
            {
                break;
            }

            $nextPageToken = $res['nextPageToken'];
            usleep(100000);
        }

        return array_values($ids);
    }

    /**
     * @param $messageId
     * @return array
     */
    public function getMessage($messageId)
    {
        $headers = [];
        $raw = $this->curl("messages/$messageId");

        if (!$payload = @$raw['payload'])
        {
        /*    if (@$GLOBALS['-SYS-VERBOSE'])
            {
                print_r($raw);
            }*/

            return [];
        }

        foreach ($payload['headers'] as $header)
        {
            $name = strtolower($header['name']);

            if (!isset ($headers[$name]))
            {
                $headers[$name] = [];
            }

            $headers[$name][] = $header['value'];
        }

        $bodies = [];
        $files = [];

        if ($payload['body'] && $payload['body']['size'])
        {
            $bodies[] = array
            (
                'size'      => $payload['body']['size'],
                'type'      => 'text/html',
                'content'   => $this->base64_decode(@$payload['body']['data']),
            );
        }

        // normalize parts
        if (isset ($payload['parts']))
        {
            foreach ($payload['parts'] as $part)
            {
                if ($part['mimeType'] == 'multipart/alternative')
                {
                    // we need to go deeper
                    foreach ($part['parts'] as $k => $subPart)
                    {
                        // that's a body => get raw data, bro

                        if (strpos($subPart['mimeType'], 'text/') === 0 && isset ($subPart['body']['attachmentId']))
                        {
                            $bodies[] = array
                            (
                                'type'      => $subPart['mimeType'],
                                'size'      => $subPart['body']['size'],
                                'content'   => $this->getAttachmentData($messageId, $subPart['body']['attachmentId']),
                                'file_id'   => null,
                            );
                        }
                        else
                        {
                            $bodies[] = array
                            (
                                'type'      => $subPart['mimeType'],
                                'size'      => $subPart['body']['size'],
                                'content'   => $this->base64_decode(@$subPart['body']['data']),
                                'file_id'   => @$subPart['body']['attachmentId'],
                            );
                        }

                        unset ($part['parts'][$k]);
                    }
                }
            }

            $bodiesEmpty = empty ($bodies);

            foreach ($payload['parts'] as $part) if ($part)
            {
                if ($bodiesEmpty)
                {
                    if (!isset ($part['body']['attachmentId']))
                    {
                        // this is most probably a normal body
                        $bodies[] = array
                        (
                            'type'      => $part['mimeType'],
                            'size'      => $part['body']['size'],
                            'content'   => $this->base64_decode(@$part['body']['data']),
                        );
                    }
                    else
                    {
                        $files[] = array
                        (
                            'type'      => $part['mimeType'],
                            'name'      => $part['filename'],
                            'size'      => $part['body']['size'],
                            // 'content'   => $this->getAttachmentData($messageId, $part['body']['attachmentId']),
                            'file_id'   => @$part['body']['attachmentId'],
                        );
                    }
                }
                elseif ($part['mimeType'] != 'multipart/alternative')
                {
                    $files[] = array
                    (
                        'type'      => $part['mimeType'],
                        'name'      => $part['filename'],
                        'size'      => $part['body']['size'],
                        'file_id'   => @$part['body']['attachmentId'],
                    );
                }
            }
        }

        if (isset ($headers['date'][0]))
        {
            $date = strtotime($headers['date'][0]);
        }
        else
        {
            $date = round($raw['internalDate'] / 1000);
        }

        return array
        (
            'message_id' => $messageId,
            'subject'    => @$headers['subject'][0] ?: '',      // subject may be absent sometimes
            'addresses'  => $this->getAddresses($headers),
            'body'       => $bodies,
            'headers'    => $headers,
            'files'      => $files,
            'date'       => $date,
            'folders'    => $raw['labelIds'],
        );
    }

    /**
     * @param $messageId
     * @param $fileId
     * @return string
     */
    public function getFileData($messageId, $fileId)
    {
        $data = $this->getMessage($messageId);

        if (@$data['files'][$fileId]['content'])
        {
            return $data['files'][$fileId]['content'];
        }

        return @$this->base64_decode($this->curl("messages/$messageId/attachments/{$data['files'][$fileId]['file_id']}")['data']);
    }

    /**
     * @param $messageId
     * @param $attachmentId
     * @return string
     */
    public function getAttachmentData($messageId, $attachmentId)
    {
        return @$this->base64_decode($this->curl("messages/$messageId/attachments/$attachmentId")['data']);
    }

    /**
     * @param $url
     * @return array
     */
    private function curl($url)
    {
        $ch = curl_init('https://www.googleapis.com/gmail/v1/users/me/' . $url . (strpos($url, '?') === false ? '?' : '&') . 'oauth_token=' . $this->accessToken['access_token']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);

        if ($output === false)
        {
            echo 'Curl error: ' . curl_error($ch) . "\n";
        }

        $res = json_decode($output, true) ?:[];
        curl_close($ch);
        return $res;
    }
}
