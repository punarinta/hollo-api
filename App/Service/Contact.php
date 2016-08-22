<?php

namespace App\Service;

/**
 * The class is still necessary for Contact abstraction to work with Context.IO
 *
 * Class Contact
 * @package App\Service
 */
class Contact extends Generic
{
     /**
     * Checks if it's necessary to sync any contact and performs that sync
     *
     * @param null $userId
     * @param bool $verbose
     * @param bool $force
     * @return array
     */
    public function syncAll($userId = null, $verbose = false, $force = false)
    {
        $countMsg = 0;
        $countContacts = 0;
        $limit = 250;
        $offset = 0;

        $userId = $userId ?: \Auth::user()->id;

        // even if it's you it's important to get an exact value of sync time
        $user = \Sys::svc('User')->findById($userId, true);
        $lastSyncTs = $user->last_sync_ts ?: 1;

        // ext sync
        \Sys::svc('User')->syncExt($user);

        // indicate started transaction
        $user->is_syncing = 1;
        \Sys::svc('User')->update($user);

        $params =
        [
            'limit' => $limit
        ];

        if (!$force)
        {
            $params['active_after'] = abs($lastSyncTs - 86400);
        }

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
                            'last_ts'   => 0,
                        ));

                        $syncCount = $row['count'];
                    }
                }

                // TODO: $syncCount check is very doubtful here
                if ($syncCount || $force)
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

            if ($contact->muted)
            {
                $syncCount = 0;
            }
            else
            {
                $syncCount = \Sys::svc('Message')->syncAll($user, $contact, 0, false, $force);

                // sleep a bit to prevent API request queue growth
                usleep(600000);
            }

            // update count only after message sync is done
            $contact->count = $row['count'];
            $contact->read = $contact->muted;
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
     * Checks if contact list is empty
     *
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
     * Check if the contact is muted by default or not
     *
     * @param $email
     * @return bool
     */
    public function isMuted($email)
    {
        $email = explode('@', $email);

        if (in_array($email[0],
        [
            'account',
            'admin',
            'automailer',
            'bekraftelse',
            'billing',
            'booking',
            'career',
            'contact',
            'demo',
            'delivery',
            'donate',
            'email',
            'event',
            'forum',
            'hello',
            'help',
            'info',
            'inform',
            'invite',
            'mail',
            'marketing',
            'medlem',
            'member',
            'messages',
            'nobody',
            'online',
            'post',
            'postmaster',
            'reklama',
            'robot',
            'service',
            'student',
            'subscription',
            'team',
            'webmaster',
            'weekly',
            'welcome',
        ])
            || strpos($email[0], 'customer') !== false
            || strpos($email[0], 'feedback') !== false
            || strpos($email[0], 'mailer') !== false
            || strpos($email[0], 'message') !== false
            || strpos($email[0], 'news') !== false
            || strpos($email[0], 'notif') !== false
            || strpos($email[0], 'nyhet') !== false
            || strpos($email[0], 'regist') !== false
            || strpos($email[0], 'reply') !== false
            || strpos($email[0], 'sales') !== false
            || strpos($email[0], 'support') !== false
            || strpos($email[0], 'survey') !== false
            || strpos($email[0], 'update') !== false
        )
        {
            return true;
        }
        elseif (count($email) === 2)
        {
            // lookup the email in the spam database and force 'muted' to 1 if found

            if (count($rows = \DB::rows('SELECT * FROM muted WHERE domain=?', [$email[1]])))
            {
                if (!$rows[0]->user)
                {
                    return true;
                }
                else
                {
                    foreach ($rows as $row)
                    {
                        if ($row->user == $email[0])
                        {
                            return true;
                        }
                    }
                }
            }
        }

        // emails consisting from numbers only are probably not what you want
        if (intval($email[0]) > 0) return true;

        return false;
    }
}
