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
            $params['subject'] = "/(.*:\s)$subject/";
        }

        foreach ($this->conn->listMessages(\Auth::user()->account_id, $params)->getData() as $row)
        {
            $row['body'][0]['content'] = $this->clearContent($row['body'][0]['content'], $email);

            $items[] = array
            (
                'ts'        => $row['date'],
                'body'      => $row['body'][0],
                'subject'   => $this->clearSubject($row['subject']),
                'from'      => $row['addresses']['from']['email'],
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

    protected function clearContent($content, $email)
    {
        // remove email
        $email = str_replace('.', '\.', $email);
        if (preg_match("/^.*(<$email\s*>).*$/s", $content, $matches))
        {
            $content = str_replace($matches[1], '', $content);
        }

        // Remove quoted lines (lines that begin with '>').
        $content = preg_replace("/(^\w.+:\n)?(^>.*(\n|$))+/mi", '', $content);

        $quoteHeadersRegex = array
        (
            '/^(On\s.+?wrote:).*$/ms',
            '/^(Den\s.+?skrev:).*$/ms',
        );

        foreach ($quoteHeadersRegex as $regex)
        {
            $content = preg_replace($regex, '', $content);
        }

        // Remove lines like '----- Original Message -----' (some other clients).
        // Also remove lines like '--- On ... wrote:' (some other clients).
        $content = preg_replace("/^---.*$/mi", '', $content);

        return trim($content);
    }
}
