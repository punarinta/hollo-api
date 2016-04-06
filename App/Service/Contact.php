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

        foreach ($this->conn->listContacts(\Auth::user()->context_id, ['limit' => 20, 'sort_by' => 'email', 'sort_order' => 'asc'])->getData() as $row)
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
        $this->conn->getSync(\Auth::user()->context_id);
    }

    /**
     * @return bool|int
     */
    public function isListEmpty()
    {
        $res = $this->conn->listContacts(\Auth::user()->context_id, ['limit' => 1]);

        if (!$res)
        {
            // error
            return -1;
        }

        return count($res->getData()) == 0;
    }
}
