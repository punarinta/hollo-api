<?php

namespace App\Service;

class User extends Generic
{
    protected $cache = [];

    /**
     * Overwritten creation to validate email
     *
     * @param $data
     * @return \StdClass
     * @throws \Exception
     */
    public function create($data)
    {
        if (filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false)
        {
            throw new \Exception('Cannot add user: invalid email');
        }

        return parent::create($data);
    }

    /**
     * @return array
     */
    public function findAllReal()
    {
        return $this->findAll(['roles' => ['$gt' => 0]]);
    }

    /**
     * Gets a User by its email
     *
     * @param $email
     * @param bool $realOnly
     * @return array
     */
    public function findByEmail($email, $realOnly = false)
    {
        if ($realOnly)
        {
            return $this->findOne(['email' => $email, 'roles' => ['$gt' => 0]]);
        }
        else
        {
            return $this->findOne(['email' => $email]);
        }
    }

    /**
     * Returns all the users participating in the Chat
     *
     * @param $chatId
     * @param bool $forContacts
     * @param int $exceptId
     * @return array
     */
    /*public function findByChatId($chatId, $forContacts = false, $exceptId = 0)
    {
        if ($forContacts)
        {
            // skip yourself, feed only basic data
            return \DB::rows('SELECT id, email, `name` FROM user AS u LEFT JOIN chat_user AS cu ON cu.user_id=u.id WHERE cu.chat_id=? AND u.id!=?', [$chatId, $exceptId]);
        }
        else
        {
            return \DB::rows('SELECT * FROM user AS u LEFT JOIN chat_user AS cu ON cu.user_id=u.id WHERE cu.chat_id=?', [$chatId]);
        }
    }*/

    /**
     * Lists Users known by specified User
     *
     * @param $userId
     * @param array $filters
     * @return array
     */
    /*public function findKnownBy($userId, $filters = [])
    {
        $sql = 'SELECT DISTINCT u.id, u.email, u.name FROM `user` AS u 
                LEFT JOIN chat_user AS cu1 ON cu1.user_id = u.id
                LEFT JOIN chat AS c ON c.id = cu1.chat_id
                LEFT JOIN chat_user AS cu2 ON cu2.chat_id = c.id
                WHERE cu2.user_id = ?';

        $params = [$userId];

        foreach ($filters as $filter)
        {
            switch ($filter['mode'])
            {
                case 'email':
                    $sql .= ' AND u.email LIKE ?';
                    $filter['value'] = '%' . $filter['value'] . '%';
                    break;
            }

            $params[] = $filter['value'];
        }

        $sql .= ' ORDER BY u.email';

        return \DB::rows($sql, $params);
    }*/

    /**
     * Checks if one User is known by another User
     *
     * @param $user1
     * @param $user2
     * @return bool
     */
    /*public function isKnownBy($user1, $user2)
    {
        $sql = 'SELECT 1 FROM chat_user AS cu1
                LEFT JOIN chat AS c ON c.id = cu1.chat_id
                LEFT JOIN chat_user AS cu2 ON cu2.chat_id = c.id
                WHERE cu1.user_id = ? AND cu2.user_id = ? LIMIT 1';

        return (bool) \DB::row($sql, [$user1, $user2]);
    }*/

    /**
     * Checks if one User is known by another User via first one's email
     *
     * @param $email
     * @param $user
     * @return bool
     */
    /*public function isKnownByEmail($email, $user)
    {
        $sql = 'SELECT 1 FROM user AS u
                LEFT JOIN chat_user AS cu1 ON cu1.user_id = u.id
                LEFT JOIN chat AS c ON c.id = cu1.chat_id
                LEFT JOIN chat_user AS cu2 ON cu2.chat_id = c.id
                WHERE u.email = ? AND cu2.user_id = ? LIMIT 1';

        return (bool) \DB::row($sql, [$email, $user]);
    }*/

    /**
     * Returns a particular User setting
     *
     * @param $user
     * @param null $path
     * @return null
     * @throws \Exception
     */
    public function setting($user = null, $path = null)
    {
        if (!$user)
        {
            $user = \Auth::user();
        }
        else if (!is_object($user))
        {
            if (!$user = $this->findOne(['_id' => $user]))
            {
                throw new \Exception('User does not exist');
            }
        }

        return \Sys::aPath((array)$user->settings, $path);
    }

    /**
     * Returns User's name
     *
     * @param $user
     * @return mixed
     * @throws \Exception
     */
    public function name($user = null)
    {
        if (!$user)
        {
            $user = \Auth::user();
        }
        else if (!is_object($user))
        {
            if (!$user = $this->findOne(['_id' => $user]))
            {
                throw new \Exception('User does not exist');
            }
        }

        return trim($user->settings->firstName . ' ' . $user->settings->lastName);
    }

    /**
     * Update a GMail subscription for a User
     *
     * @param $user
     * @return bool
     */
    public function subscribeToGmail($user)
    {
        // TODO: test with non-Gmail

        $settings = $user->settings;

        if (!$token = $settings->token)
        {
            return false;
        }

        $client = new \Google_Client();
        $client->setClientId(\Sys::cfg('oauth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('oauth.google.secret'));
        $client->refreshToken($token);
        $accessToken = $client->getAccessToken();
        $accessToken = json_decode($accessToken, true);

        $ch = curl_init('https://www.googleapis.com/gmail/v1/users/me/watch?oauth_token=' . $accessToken['access_token']);

        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array
        (
            'topicName' => 'projects/hollo-1271/topics/gmail',
            'labelIds'  => [/*'INBOX'*/],
        )));
        $res = curl_exec($ch);
        curl_close($ch);

        if (@$GLOBALS['-SYS-VERBOSE'])
        {
            print_r($res);
        }

        $res = json_decode($res, true) ?: [];

        $settings->historyId = $res['historyId'];
        $user->settings = $settings;
        \Sys::svc('User')->update($user);

        return true;
    }

    /**
     * Resync avatars for the User
     *
     * @param $user
     * @param null $qEmail
     * @return int
     */
    public function updateAvatars($user, $qEmail = null)
    {
        $countAvas = 0;
        $emailsPerUserLimit = 10000;

        if (!$token = $user->settings->token)
        {
            if (@$GLOBALS['-SYS-VERBOSE'])
            {
                echo "No refresh token";
            }

            return 0;
        }

        $client = new \Google_Client();
        $client->setClientId(\Sys::cfg('oauth.google.clientId'));
        $client->setClientSecret(\Sys::cfg('oauth.google.secret'));
        $client->refreshToken($token);
        $accessToken = $client->getAccessToken();
        $accessToken = json_decode($accessToken, true);

        $pageSize = 25;
        $pageStart = 1;

        // echo "Access token = {$accessToken['access_token']}\n\n";

        while ($pageStart < $emailsPerUserLimit)   // limit just to be safe
        {
            if (@$GLOBALS['-SYS-VERBOSE'])
            {
                echo "PageStart = $pageStart\n";
            }

            $url = "https://www.google.com/m8/feeds/contacts/default/full?start-index=$pageStart&max-results=$pageSize&alt=json&v=3.0&oauth_token=" . $accessToken['access_token'];

            if ($qEmail)
            {
                $url .= '&q=' . $qEmail;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $contacts = json_decode(curl_exec($ch), true) ?:[];
            curl_close($ch);

            if (isset ($contacts['feed']['entry'])) foreach ($contacts['feed']['entry'] as $contact)
            {
                if (!$email = @$contact['gd$email'][0]['address'])
                {
                    // may happen
                    continue;
                }

                if (file_exists('data/files/avatars/' . $email))
                {
                    continue;
                }

                $image = null;

                if (isset ($contact['link'][0]['href']))
                {
                    $url = $contact['link'][0]['href'];
                    $url = $url . '&access_token=' . urlencode($accessToken['access_token']);

                    $curl = curl_init($url);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 3);
                    $image = curl_exec($curl);
                    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    curl_close($curl);

                    if ($httpcode == 200 && isset ($contact['gd$email'][0]['address']))
                    {
                        /* $results[] = array
                        (
                            'name'  => $contact['title']['$t'],
                            'email' => $contact['gd$email'][0]['address'],
                            'image' => base64_encode($image),
                        ); */

                        if (@$GLOBALS['-SYS-VERBOSE'])
                        {
                            echo " * saving ava for $email...\n";
                        }

                        file_put_contents('data/files/avatars/' . $email, $image);

                        ++$countAvas;
                    }
                }
            }

            if (count($contacts['feed']['entry']) < $pageSize)
            {
                break;
            }
            else
            {
                $pageStart += $pageSize;
            }

            usleep(50000);
        }

        return $countAvas;
    }
}
