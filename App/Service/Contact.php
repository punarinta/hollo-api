<?php

namespace App\Service;

class Contact extends Generic
{
    /**
     * Returns a contact by its email and owner ID
     *
     * @param $email
     * @param $userId
     * @return null|\StdClass
     */
    public function findByEmailAndUserId($email, $userId)
    {
        return \DB::row('SELECT * FROM contact WHERE email=? AND user_id=? LIMIT 1', [$email, $userId]);
    }

    /**
     * Returns a contact by its email and owner ExtID
     *
     * @param $email
     * @param $account
     * @return null|\StdClass
     */
    public function findByEmailAndAccountId($email, $account)
    {
        return \DB::row('SELECT * FROM contact AS c LEFT JOIN user AS u ON c.user_id=u.id WHERE c.email=? AND u.ext_id=?', [$email, $account]);
    }

    /**
     * Returns a contact by t.b.d.
     *
     * @param $id
     * @param $userId
     * @return null|\StdClass
     */
    public function findByIdAndUserId($id, $userId)
    {
        return \DB::row('SELECT * FROM contact WHERE id=? AND user_id=? LIMIT 1', [$id, $userId]);
    }

    /**
     * Returns contacts associated with the current user account
     *
     * @param $userId
     * @param array $filters
     * @param null $sortBy
     * @param null $sortMode
     * @return array
     */
    public function findAllByUserId($userId, $filters = [], $sortBy = null, $sortMode = null)
    {
        $sql = 'SELECT * FROM contact WHERE user_id=?';
        $params = [$userId];
        
        foreach ($filters as $filter)
        {
            switch ($filter['mode'])
            {
                case 'name':
                    $sql .= ' AND `name` LIKE ?';
                    break;

                case 'email':
                    $sql .= ' AND email LIKE ?';
                    break;

                case 'muted':
                    $sql .= ' AND muted=?';
                    break;
            }

            $params[] = $filter['value'];
        }

        if ($sortBy)
        {
            if ($sortBy == 'name')
            {
                $sql .= ' ORDER BY `name`';
            }
            elseif ($sortBy == 'email')
            {
                $sql .= ' ORDER BY email';
            }
            elseif ($sortBy == 'lastTs')
            {
                $sql .= ' ORDER BY last_ts';
            }

            if ($sortMode == 'desc')
            {
                $sql .= ' DESC';
            }
        }
        else
        {
            $sql .= ' ORDER BY `read` ASC, email ASC';
        }

        return \DB::rows($sql, $params);
    }

    /**
     * Checks if it's necessary to sync any contact and performs that sync
     *
     * @param null $userId
     * @param bool $verbose
     * @return array
     */
    public function syncAll($userId = null, $verbose = false)
    {
        $countMsg = 0;
        $countContacts = 0;
        $limit = 250;
        $offset = 0;

        $userId = $userId ?: \Auth::user()->id;

        // even if it's you it's important to get an exact value of sync time
        $user = \Sys::svc('User')->findById($userId, true);
        $lastSyncTs = $user->last_sync_ts ?: 1;

        // indicate started transaction
        $user->is_syncing = 1;
        \Sys::svc('User')->update($user);

        $params =
        [
            'limit'         => $limit,
            'active_after'  => $lastSyncTs,
        ];

        $contactsToSync = [];

        while (1)
        {
            $params['offset'] = $offset;

            $data = $this->conn->listContacts($user->ext_id, $params);
            $data = $data->getData();
            $rows = isset ($data['matches']) ? $data['matches'] : [];

            foreach ($rows as $row)
            {
                $syncCount = 0;
                $email = $row['email'];

                if ($verbose)
                {
                    echo "Checking '$email'... ";
                }

                if ($contact = \Sys::svc('Contact')->findByEmailAndUserId($email, $user->id))
                {
                    // contact exists -> check 'count'
                    if ($row['count'] != $contact->count)
                    {
                        // sync messages
                        $syncCount = $row['count'] - $contact->count;
                    }
                }
                else
                {
                    // don't keep your own messages to yourself
                    if ($email != $user->email)
                    {
                        // contact doesn't exist -> insert
                        $contact = $this->create(array
                        (
                            'user_id'   => $user->id,
                            'email'     => $email,
                            'name'      => isset ($row['name']) ? $row['name'] : null,
                            'count'     => 0,
                            'muted'     => 0,
                            'read'      => 1,
                        ));

                        $syncCount = $row['count'];
                    }
                }

                if ($syncCount)
                {
                    $contactsToSync[] = $row;
                }

                if ($verbose)
                {
                    if ($contact->muted) echo "\033[01;31m(muted) \033[0m";
                    echo "$syncCount new.\n";
                }
            }

            $countContacts += count($rows);

            if (count($rows) < $limit)
            {
                break;
            }

            $offset += $limit;
        }

        // sync messages for those contacts
        foreach ($contactsToSync as $row)
        {
            $email = $row['email'];

            if ($verbose)
            {
                echo "Syncing '$email'... ";
            }

            // contact always exists at this point
            $contact = \Sys::svc('Contact')->findByEmailAndUserId($email, $user->id);

            $syncCount = $contact->muted ? 0 : \Sys::svc('Message')->syncAll($user, $contact);

            // update count only after message sync is done
            $contact->count = $row['count'];
            $contact->read = false;
            $contact->last_ts = max($row['last_received'], $row['last_sent']);      // NB: not a sync, but a mailing event!
            \Sys::svc('Contact')->update($contact);

            if ($verbose)
            {
                if ($contact->muted) echo "\033[01;31m(muted) \033[0m";
                echo "$syncCount new.\n";
            }

            $countMsg += $syncCount;
        }

        // indicate completed transaction
        $user->is_syncing = 0;

        // update last sync time
        $user->last_sync_ts = time();
        \Sys::svc('User')->update($user);

        return array
        (
            'contacts' => $countContacts,
            'messages' => $countMsg,
        );
    }

    /**
     * @return bool|int
     */
    public function isListEmpty()
    {
        $res = $this->conn->listContacts(\Auth::user()->ext_id, ['limit' => 1]);

        if (!$res)
        {
            // error
            return -1;
        }

        return count($res->getData()) == 0;
    }

    /**
     * Before inserting the record check if it should be muted or not
     *
     * @param $array
     * @return \StdClass
     */
    public function create($array)
    {
        $email = explode('@', $array['email']);

        if (in_array($email[0], ['no-reply', 'no_reply', 'news', 'newsletter', 'info', 'dontreply', 'donotreply']) || strpos($email[0], 'noreply') === 0)
        {
            $array['muted'] = 1;
        }
        elseif (count($email) === 2)
        {
            // lookup the email in the spam database and force 'muted' to 1 if found

            if (count($rows = \DB::rows('SELECT * FROM muted WHERE domain=?', [$email[1]])))
            {
                if (!$rows[0]->user)
                {
                    $array['muted'] = 1;
                }
                else
                {
                    foreach ($rows as $row)
                    {
                        if ($row->user == $email[0])
                        {
                            $array['muted'] = 1;
                            break;
                        }
                    }
                }
            }
        }

        return parent::create($array);
    }
}
