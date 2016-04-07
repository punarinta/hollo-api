<?php

namespace App\Service;

class Contact extends Generic
{
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
     * Calls data sync
     */
    public function sync()
    {
        $this->conn->getSync(\Auth::user()->ext_id);
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
