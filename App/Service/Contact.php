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
     * @param null $filterBy
     * @param null $filter
     * @param null $sortBy
     * @param null $sortMode
     * @return array
     */
    public function findAllByUserId($userId, $filterBy = null, $filter = null, $sortBy = null, $sortMode = null)
    {
        $sql = 'SELECT * FROM contact WHERE user_id=?';
        $params = [$userId];
        
        if ($filterBy == 'name')
        {
            $sql .= ' AND `name` LIKE ?';
            $params[] = $filter;
        }
        elseif ($filterBy == 'email')
        {
            $sql .= ' AND email LIKE ?';
            $params[] = $filter;
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
        $lastSyncTs = $user->last_sync_ts;

        // indicate started transaction
        $user->is_syncing = 1;
        \Sys::svc('User')->update($user);

        while (1)
        {
            $data = $this->conn->listContacts($user->ext_id, ['active_after' => $lastSyncTs]);
            $data = $data->getData();
            $rows = isset ($data['matches']) ? $data['matches'] : [];

            foreach ($rows as $row)
            {
                $syncCount = 0;
                $email = $row['email'];

                if ($verbose)
                {
                    echo "Syncing '$email'... ";
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
                    // contact doesn't exist -> insert
                    $contact = \Sys::svc('Contact')->create(array
                    (
                        'user_id'   => $user->id,
                        'email'     => $email,
                        'name'      => isset ($row['name']) ? $row['name'] : null,
                        'count'     => 0,
                    ));

                    $syncCount = $row['count'];
                }

                if ($syncCount)
                {
                    $countMsg += \Sys::svc('Message')->syncAll($user, $contact);

                    // update count only after message sync is done
                    $contact->count = $row['count'];
                    $contact->last_ts = max($row['last_received'], $row['last_sent']);
                    \Sys::svc('Contact')->update($contact);
                }

                if ($verbose)
                {
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
}
