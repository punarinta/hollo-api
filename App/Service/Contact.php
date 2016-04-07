<?php

namespace App\Service;

class Contact extends Generic
{
    /**
     * Returns a contact by its email
     *
     * @param $email
     * @return null|\StdClass
     */
    public function findByEmail($email)
    {
        return \DB::row('SELECT * FROM contact WHERE email=? AND user_id=? LIMIT 1', [$email, \Auth::user()->id]);
    }

    /**
     * Returns contacts associated with the current user account
     *
     * @return mixed
     */
    public function findForMe()
    {
        $items = [];

        if (!$data = $this->conn->listContacts(\Auth::user()->ext_id, ['limit' => 20, 'sort_by' => 'email', 'sort_order' => 'asc']))
        {
            return [];
        }

        foreach ($data->getData() as $row)
        {
            $items[] = $row;
        }

        return $items[1];
    }

    /**
     * Checks if it's necessary to sync a contact and performs this sync
     *
     * @param $email
     * @return array
     * @throws \Exception
     */
    public function sync($email)
    {
        $syncCount = 0;
    //    $lastSyncTs = \Auth::user()->last_sync_ts;

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
            \Sys::svc('Message')->sync($email, $syncCount);
        }

        return array
        (
            'count' => 0,
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
