<?php

namespace App\Controller;

class Contact extends Generic
{
    /**
     * Lists your contacts
     *
     * @doc-var     (string) sortBy     - Sorting key. Options are 'name', 'email', 'lastTs'.
     * @doc-var     (string) sortMode   - Sorting mode. Options are 'asc', 'desc'.
     * @doc-var     (string) filterBy   - Filtering mode. Options are 'name', 'email'.
     * @doc-var     (string) filter     - Filter string.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function find()
    {
        // issue an immediate sync
        \Sys::svc('Resque')->addJob('SyncContacts', ['user_id' => \Auth::user()->id]);

        $items = [];

        foreach (\Sys::svc('Contact')->findAllByUserId(\Auth::user()->id, \Input::data('filterBy'), \Input::data('filter')) as $item)
        {
            $items[] = array
            (
                'id'        => $item->id,
                'name'      => $item->name,
                'email'     => $item->email,
                'count'     => $item->count,
                'lastTs'    => $item->last_ts,
            );
        }

        if ($sortBy = \Input::data('sortBy'))
        {
            $sortMode = \Input::data('sortMode') === 'desc' ? -1 : 1;

            if ($sortBy == 'name')
            {
                usort($items, function ($a, $b) use ($sortMode)
                {
                    return $a['name'] > $b['name'] ? $sortMode : -$sortMode;
                });
            }
            elseif ($sortBy == 'email')
            {
                // default sorting in SQL
            }
            elseif ($sortBy == 'lastTs')
            {
                usort($items, function ($a, $b) use ($sortMode)
                {
                    return $a['lastTs'] > $b['lastTs'] ? $sortMode : -$sortMode;
                });
            }
            else
            {
                throw new \Exception('Sorting mode is not supported.');
            }
        }

        return $items;
    }

    /**
     * Update contact info
     *
     * @doc-var     (int) id!           - Contact ID.
     * @doc-var     (string) name       - Contact name.
     *
     * @throws \Exception
     */
    static public function update()
    {
        if (!$id = \Input::data('id'))
        {
            throw new \Exception('No ID provided.');
        }

        if (!$contact = \Sys::svc('Contact')->findByIdAndUserId($id, \Auth::user()->id))
        {
            throw new \Exception('Contact not found.');
        }
        
        if (\Input::data('name') !== null) $contact->name = \Input::data('name');

        \Sys::svc('Contact')->update($contact);
    }
}
