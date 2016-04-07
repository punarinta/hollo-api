<?php

namespace App\Service;

class Message extends Generic
{
    /**
     * @param $extId
     * @return null|\StdClass
     */
    public function findByExtId($extId)
    {
        return \DB::row('SELECT * FROM message WHERE ext_id = ? LIMIT 1', [$extId]);
    }

    /**
     * Returns messages associated with a contact. May filter by subject.
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

        foreach ($this->conn->listMessages(\Auth::user()->ext_id, $params)->getData() as $row)
        {
            $files = null;
            $row['body'][0]['content'] = $this->clearContent($row['body'][0]['content'], $email);

            if (isset ($row['files']))
            {
                $files = [];
                foreach ($row['files'] as $file)
                {
                    $files[] = array
                    (
                        'name'  => $file['file_name'],
                        'type'  => $file['type'],
                        'size'  => $file['size'],
                        'extId' => $file['file_id'],
                    );
                }
            }

            $items[] = array
            (
                'ts'        => $row['date'],
                'body'      => $row['body'][0],
                'subject'   => $this->clearSubject($row['subject']),
                'from'      => $row['addresses']['from']['email'],
                'files'     => $files,
            );
        }

        return $items;
    }

    /**
     * Performs message sync for a specified email
     *
     * @param $contact
     * @param $lastSyncTs
     * @return int|null
     * @throws \Exception
     */
    public function sync($contact, $lastSyncTs)
    {
        $count = 0;
        $limit = 100;
        $offset = 0;

        $params =
        [
            'include_body'  => 1,
            'limit'         => $limit,
            'date_after'    => $lastSyncTs,
            'email'         => $contact->email,
        ];

        // paginate requests
        while (1)
        {
            $params['offset'] = $offset;
            
            $rows = $this->conn->listMessages(\Auth::user()->ext_id, $params)->getData();

            foreach ($rows as $row)
            {
                // check if message is present and sync if necessary

                // get message ext_id
                $extId = explode('/', $row['resource_url']);
                $extId = end($extId);

                if (!$message = \Sys::svc('Message')->findByExtId($extId))
                {
                    \Sys::svc('Message')->create(array
                    (
                        'ts'            => $row['date'],
                        'body'          => $row['body'][0]['content'],   // should be kept as is, without filtering
                        'ext_id'        => $extId,
                        'contact_id'    => $contact->id,
                    ));
                }
                else
                {
                    // strange but possible
                    --$count;
                }
            }

            $count += count($rows);
            
            if (count($rows) < $limit)
            {
                break;
            }

            $offset += $limit;
        }

        return $count;
    }

    protected function clearSubject($subject)
    {
        $items = ['Re:', 'Fwd:', 'Fw:'];

        foreach ($items as $item)
        {
            if (strpos($subject, $item, 0) === 0)
            {
                $subject = str_ireplace($item, '', $subject);
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

        // remove zero-width space
        $content = str_replace("\xE2\x80\x8B", '', $content);

        return trim($content);
    }
}
