<?php

namespace App\Service;

class Message extends Generic
{
    /**
     * Returns messages associated with a contact
     *
     * @param $email
     * @param null $subject
     * @return array
     */
    public function findByContact($email, $subject = null)
    {
        $items = [];

        $params =
        [
            'email'         => $email,
            'sort_order'    => 'asc',
            'include_body'  => 1,
        ];

        if ($subject)
        {
            $params['subject'] = "/(.*)$subject/";
        }

        foreach ($this->conn->listMessages(\Auth::user()->account_id, $params)->getData() as $row)
        {
            $items[] = array
            (
                'ts'        => $row['date'],
                'body'      => $this->clearBody($row['body'][0]),
                'subject'   => $this->clearSubject($row['subject']),
            );
        }

        return $items;
    }

    protected function clearSubject($subject)
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

    protected function clearBody($body)
    {
        $body = preg_replace('#(^\w.+:\n)?(^>.*(\n|$))+#mi', '', $body);

        return $body;
    }
}
