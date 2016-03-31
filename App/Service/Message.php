<?php

namespace App\Service;

class Message extends Generic
{
    /**
     * Returns messages associated with a contact
     *
     * @param $email
     * @return array
     */
    public function findByContact($email)
    {
        $items = [];

        foreach ($this->conn->listMessages(\Auth::user()->account_id, ['include_body' => 1, 'email' => $email, 'sort_order' => 'asc'])->getData() as $row)
        {
            $items[] = array
            (
                'ts'        => $row['date'],
                'body'      => $row['body'][0],
                'subject'   => $this->stripRes($row['subject']),
            );
        }

        return $items;
    }

    protected function stripRes($subject)
    {
        $items = ['Re:', 'Fwd:'];

        foreach ($items as $item)
        {
            if (strpos($subject, $item, 0) === 0)
            {
                $subject = str_replace($item, '', $subject);
                break;
            }
        }

        return trim($subject);
    }
}
