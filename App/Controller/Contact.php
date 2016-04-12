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

        foreach (\Sys::svc('Contact')->findForMe(\Input::data('filterBy'), \Input::data('filter')) as $item)
        {
            $items[] = array
            (
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

    static public function sync()
    {
        \Sys::svc('Contact')->sync();
    }
}
