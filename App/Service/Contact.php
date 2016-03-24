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

        foreach ($this->conn->listContacts(\Auth::user()->account_id, ['limit' => 50])->getData() as $row)
        {
            $items[] = $row;
        }

        return $items[1];
    }
}
