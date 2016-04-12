<?php

namespace App\Service;

class Contact extends Generic
{
    /**
     * Returns a contact by its email
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
     * Returns contacts associated with the current user account
     *
     * @param null $filterBy
     * @param null $filter
     * @return array
     */
    public function findForMe($filterBy = null, $filter = null)
    {
        $sql = 'SELECT * FROM contact WHERE user_id=?';
        $params = [\Auth::user()->id];
        
        if ($filterBy == 'name')
        {
            $sql .= ' AND `name` LIKE ? ORDER BY last_ts DESC';
            $params[] = $filter;
        }
        elseif ($filterBy == 'email')
        {
            $sql .= ' AND `email` LIKE ? ORDER BY last_ts DESC';
            $params[] = $filter;
        }
        else
        {
            $sql .= ' ORDER BY email';
        }

        return \DB::rows($sql, $params);
    }

    /**
     * Checks if it's necessary to sync a contact and performs this sync
     *
     * @param $email
     * @return int
     * @throws \Exception
     */
    /*public function sync($email)
    {
        $syncCount = 0;

        // get remote contact by email
        if (!$data = $this->conn->getContact(\Auth::user()->ext_id, ['email' => $email]))
        {
            throw new \Exception('Error fetching remote contact');
        }

        $data = $data->getData();

        if ($contact = \Sys::svc('Contact')->findByEmail($email))
        {
            // contact exists -> check 'count'
            if ($data['count'] != $contact->count)
            {
                // sync messages
                $syncCount = $data['count'] - $contact->count;
            }
        }
        else
        {
            // contact doesn't exist -> insert
            \Sys::svc('Contact')->create(array
            (
                'user_id'   => \Auth::user()->id,
                'email'     => $email,
                'name'      => $data['name'],
                'count'     => $data['count'],
            ));

            $syncCount = $data['count'];
        }

        if ($syncCount)
        {
            return \Sys::svc('Message')->sync($contact);
        }

        return $syncCount;
    }*/

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
                        'name'      => $row['name'],
                        'count'     => 0,
                    ));

                    $syncCount = $row['count'];
                }

                if ($syncCount)
                {
                    $countMsg += \Sys::svc('Message')->sync($user, $contact);

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
