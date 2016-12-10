<?php

namespace App\Service;

use MongoDB\BSON\ObjectID;

class Chat extends Generic
{
    protected static $class_name = 'chat';

    /**
     * Initializes a new Chat with a specific set of participants
     *
     * @param array $emails
     * @param array $names
     * @return mixed|null
     */
    public static function init($emails = [], $names = [])
    {
        $emails = array_unique($emails);

        // check just in case
        if ($chat = self::findByEmails($emails))
        {
            return $chat;
        }

        $muteThis = false;
        $chatUsers = [];

        foreach ($emails as $email)
        {
            if (Contact::isMuted($email))
            {
                $muteThis = true;
            }
        }

        // create users first
        foreach ($emails as $email)
        {
            if (!$user = User::findOne(['email' => $email]))
            {
                $userStructure = ['email' => $email];
                if (isset ($names[$email]) && $names[$email] != $email)
                {
                    $userStructure['name'] = $names[$email];
                }

                $user = User::create($userStructure);
            }

            $chatUsers[] = (object)
            [
                'id'    => $user->_id,
                'read'  => 1,
                'muted' => (int) $muteThis,
            ];
        }

        return self::create(['users' => $chatUsers]);
    }

    /**
     * Returns chats associated with the current user account
     *
     * @param $userId
     * @param array $filters
     * @return array
     */
    public static function findAllByUserId($userId, $filters = [])
    {
        $mongoFilter = ['users' => ['$elemMatch' => ['id' => $userId]]];

        foreach ($filters as $filter)
        {
            if ($filter['mode'] == 'muted')
            {
                $mongoFilter['users']['$elemMatch']['muted'] = (int) $filter['value'];
            }

            if ($filter['mode'] == 'read')
            {
                $mongoFilter['users']['$elemMatch']['read'] = (int) $filter['value'];
            }

            if ($filter['mode'] == 'name')
            {
                $mongoFilter['name'] = $filter['value'];
            }
        }

        return self::findAll
        (
            $mongoFilter,
            [
            //    'projection' => ['messages' => ['$slice' => -1]],   // get last message only
                'sort' => ['lastTs' => -1],
            ]
        );
    }

    /**
     * Gets 'read' and 'muted' flags for given Chat and User
     *
     * @param $chat
     * @param $userId
     * @return null|\stdClass
     */
    public static function getFlags($chat, $userId)
    {
        if (!$userId)
        {
            return null;
        }

        if (!is_object($chat))
        {
            $chat = self::findOne(['_id' => $chat], ['projection' => ['users' => 1]]);
        }

        foreach ($chat->users ?? [] as $userItem)
        {
            if ($userItem->id == $userId)
            {
                $flags = new \stdClass;
                $flags->read = (int) $userItem->read;
                $flags->muted = (int) $userItem->muted;
                return $flags;
            }
        }

        return null;
    }

    /**
     * Sets 'read' or/and 'muted' flags for given Chat and User
     *
     * @param $chat
     * @param $userId
     * @param $flags
     * @return bool
     */
    public static function setFlags($chat, $userId, $flags)
    {
        if (!is_object($chat))
        {
            $chat = self::findOne(['_id' => $chat], ['projection' => ['users' => 1]]);
        }

        $chatUsers = $chat->users;

        foreach ($chatUsers ?? [] as $k => $userRow)
        {
            if ($userRow->id == $userId)
            {
                $chatUsers[$k]->read = (int) $flags->read;
                $chatUsers[$k]->muted = (int) $flags->muted;
                self::update($chat, ['users' => $chatUsers]);

                return true;
            }
        }

        return false;
    }

    /**
     * Sets 'muted' flag for given Chat and User
     *
     * @param $chat
     * @param $userId
     * @param $muted
     * @return bool
     */
    public static function setMutedFlag($chat, $userId, $muted)
    {
        if (!is_object($chat))
        {
            $chat = self::findOne(['_id' => $chat], ['projection' => ['users' => 1]]);
        }

        $chatUsers = $chat->users;

        foreach ($chatUsers ?? [] as $k => $userRow)
        {
            if ($userRow->id == $userId)
            {
                $chatUsers[$k]->muted = (int) $muted;
                self::update($chat, ['users' => $chatUsers]);

                return true;
            }
        }

        return false;
    }

    /**
     * Sets 'read' flag for given Chat and User
     *
     * @param $chat
     * @param $userId
     * @param $read
     */
    public static function setReadFlag($chat, $userId, $read)
    {
        if (!is_object($chat))
        {
            $chat = self::findOne(['_id' => $chat], ['projection' => ['users' => 1]]);
        }

        $chatUsers = json_decode(json_encode($chat->users ?? []));

        foreach ($chatUsers ?? [] as $k => $userRow)
        {
            if (!$userId)
            {
                $chatUsers[$k]->read = (int) $read;
            }
            else
            {
                if ($userId[0] == '-')
                {
                    if ($userRow->id != $userId)
                    {
                        $chatUsers[$k]->read = (int) $read;
                    }
                }
                else
                {
                    if ($userRow->id == $userId)
                    {
                        $chatUsers[$k]->read = (int) $read;
                    }
                }
            }
        }

        if ($chatUsers != $chat->users)
        {
            self::update($chat, ['users' => $chatUsers]);
        }
    }

    /**
     * Counts Users in the Chat
     *
     * @param $chat
     * @return mixed
     */
    public static function countUsers($chat)
    {
        if (!is_object($chat))
        {
            $chat = self::findOne(['_id' => $chat], ['projection' => ['users' => 1]]);
        }

        return count($chat->users ?? []);
    }

    /**
     * Check is User has access to Chat
     *
     * @param $chat
     * @param $userId
     * @return bool
     */
    public static function hasAccess($chat, $userId)
    {
        foreach ($chat->users ?? [] as $userItem)
        {
            if ($userItem->id == $userId)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Drops a User from a Chat and removes the Chat if necessary
     *
     * @param $chatId
     * @param $userId
     * @return bool
     */
    public static function dropUser($chatId, $userId)
    {
        if (!$chat = self::findOne(['_id' => new ObjectID($chatId)]))
        {
            return false;
        }

        $chatUsers = $chat->users;

        foreach ($chatUsers ?? [] as $k => $userRow)
        {
            if ($userRow->id == $userId)
            {
                if (count($chatUsers) <= 2)
                {
                    // one of them were User => kill the whole chat
                    self::delete($chat);
                }
                else
                {
                    unset ($chatUsers[$k]);
                    self::update($chat, ['users' => $chatUsers]);
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Finds a Chat by emails of all its participants
     *
     * @param array $emails
     * @return mixed|null
     */
    public static function findByEmails($emails = [])
    {
        $ids = [];

        foreach ($emails as $email)
        {
            if (!$user = User::findOne(['email' => $email]))
            {
                return null;
            }

            $ids[] = $user->_id;
        }

        return self::findOne(['users.id' => ['$all' => $ids], 'users' => ['$size' => count($emails)]]);
    }
}
