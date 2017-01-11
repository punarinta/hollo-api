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
    /**
     * @var mixed
     */
    protected static $mail = null;
    protected static $setup = false;
    protected static $messageExisted = false;

    /**
     * Sets up message sending for a particular thread
     *
     * @param $user
     * @param null $chat
     * @param null $tempMsgId
     * @param int $transport
     * @return bool
     * @throws \Exception
     */
    public static function setupThread($user, $chat = null, $tempMsgId = null, $transport = 0)
    {
        if (!is_object($user))
        {
            $user = User::findOne(['_id' => new ObjectID($user)]);
        }

        if (!$user) return false;

        self::$mail = new \PHPMailer();

        $out = MailService::getCfg($user->settings->svc, 'out');

        if ($out->oauth)
        {
            self::$mail = new \PHPMailerOAuth();
            self::$mail->AuthType = 'XOAUTH2';
            self::$mail->oauthUserEmail = $user->email;
            self::$mail->oauthClientId = \Sys::cfg('oauth.google.clientId');
            self::$mail->oauthClientSecret = \Sys::cfg('oauth.google.secret');
            self::$mail->oauthRefreshToken = $_SESSION['-AUTH']['mail']['token'];
        }
        else
        {
            self::$mail = new \PHPMailer();
            self::$mail->Username = $_SESSION['-AUTH']['mail']['user'];
            self::$mail->Password = $_SESSION['-AUTH']['mail']['pass'];
        }

        // TODO: support overridden host/port
        self::$mail->Host = $out->host;
        self::$mail->Port = $out->port;

        self::$mail->SMTPSecure = $out->enc;
        self::$mail->SMTPAuth = true;
    //    self::$mail->SMTPDebug = 4;
        self::$mail->isSMTP();
        self::$mail->CharSet = 'UTF-8';

        self::$mail->Subject = '';

        if ($tempMsgId)
        {
            self::$mail->addCustomHeader('X-Temporary-ID: ' . $tempMsgId);

            // save temp message ID into AltBody
            self::$mail->AltBody = $tempMsgId;
        }

        // quote old messages only if transport is 'classic'
        if ($chat && $transport == 0)
        {
            // do not continue if no real message found in the chat
            if ($message = Message::findByLastRealByChat($chat))
            {
                $refUser = User::findOne(['_id' => new ObjectID($message->refId)]);

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

                        self::$mail->addCustomHeader('Message-ID: ' . \Text::GUID_v4() . '@' . \Sys::cfg('mailless.this_server'));
                        self::$mail->addCustomHeader('In-Reply-To: ' . $emailMessageId);
                        self::$mail->addCustomHeader('References: ' . implode(' ', $refs));

                        self::$mail->Subject = 'Re: ' . $data['subject'];

                        if (isset ($data['body']))
                        {
                            self::$messageExisted = true;

                            $content = mb_convert_encoding($data['body'][0]['content'], 'UTF-8');

                            $body = [];
                            foreach (preg_split("/\r\n|\n|\r/", $content) as $line)
                            {
                                $body[] = '> ' . $line;
                            }

                            $ts = date('r', $data['date']);
                            $name = explode('@', $data['addresses']['from']['email']);
                            self::$mail->Body = "\nOn {$ts}, {$name[0]} <{$data['addresses']['from']['email']}> wrote:\n\n" . implode("\n", $body);
                        }
                    }
                }
            }
        }

        $name = User::name($user);
        self::$mail->setFrom($user->email, $name);
        self::$mail->addReplyTo($user->email, $name);

        self::$setup = true;

        return true;
    }

    /**
     * Sends a message, can add more recipients
     *
     * @param $chat
     * @param $body
     * @param null $subject
     * @param array $attachments
     * @param int $transport
     * @return mixed
     * @throws \Exception
     */
    public static function send($chat, $body, $subject = null, $attachments = [], $transport = 0)
    {
        $tempFiles = [];

        if ($transport != 2)
        {
            if (!self::$setup)
            {
                throw new \Exception('Sending was not setup');
            }

            if ($body)
            {
                // body may be empty in case of a files only post
                self::$mail->Body = $body . "\n" . self::$mail->Body;
            }

            if ($subject)
            {
                if (!self::$messageExisted || Message::clearSubject(self::$mail->Subject) != $subject)
                {
                    // this means the mail did not exist or message existed, but this one is new
                    self::$mail->Body = $body;
                    self::$mail->Subject = $subject;
                }
            }
            else
            {
                // no subject -> do not quote
                self::$mail->Body = $body;
            }
        }

        $userIds = [];

        foreach ($chat->users ?? [] as $userRow)
        {
            if (\Auth::user()->_id == $userRow->id)
            {
                continue;
            }

            $user = User::findOne(['_id' => new ObjectID($userRow->id)]);
            self::$mail->addAddress($user->email, @$user->name);

            // collect user IDs for IM notification
            $userIds[] = $user->_id;

            Notify::firebase(array
            (
                'to'           => '/topics/user-' . $userRow->id,
                'collapse_key' => 'new_message',
                'priority'     => 'high',

                'notification' => array
                (
                    'title' => $subject,
                    'body'  => $body,
                    'icon'  => 'fcm_push_icon'
                ),

                'data' => array
                (
                    'cmd'    => 'chat:update',
                    'authId' => $userRow->id,
                    'chatId' => $chat->_id,
                ),
            ));
        }

        Notify::im(
        [
            'cmd'       => 'chat:update',
            'userIds'   => $userIds,
            'chatId'    => $chat->_id,
        ]);

        if ($transport != 2)
        {
            if (count($userIds) == 1)
            {
                // use text/html as the main body
                self::$mail->isHTML(true);

                $tempMessageId = self::$mail->AltBody;

                $trackingPixel = '<img src="https://' . \Sys::cfg('mailless.app_domain') . '/api/track?&token=' . $chat->_id . $tempMessageId . $userIds[0] . '" />';
                self::$mail->AltBody = self::$mail->Body;
                self::$mail->Body = $trackingPixel . nl2br(self::$mail->Body);
            }
            else
            {
                self::$mail->AltBody = '';
            }

            foreach ($attachments as $file)
            {
                // save file first
                $path = tempnam('data/temp', 'upl-');

                $f = fopen($path, 'wb');
                stream_filter_append($f, 'convert.base64-decode');
                fwrite($f, substr($file['b64'], strpos($file['b64'], ',') + 1));
                fclose($f);

                $tempFiles[] = $path;

                self::$mail->addAttachment($path, $file['name'], 'base64', $file['type']);
            }

            if (!self::$mail->send())
            {
                throw new \Exception(self::$mail->ErrorInfo);
            }

            // cleanup possible temporary files
            foreach ($tempFiles as $file)
            {
                unlink($file);
            }

            return [self::$mail, self::$mail->getCustomHeaders(), self::$mail->getToAddresses(), self::$mail->getCcAddresses(), self::$mail->getAttachments()];
        }
        else
        {
            return true;
        }
    }
}
