<?php

namespace App\Controller;

class Contact extends Generic
{
    /**
     * Lists your contacts
     *
     * @doc-var     (string) sortBy         - Sorting key. Options are 'name', 'email', 'lastTs'.
     * @doc-var     (string) sortMode       - Sorting mode. Options are 'asc', 'desc'.
     * @doc-var     (array) filters         - Array of 'filter'.
     * @doc-var     (string) filter[].mode  - Filtering mode. Options are 'muted', 'name', 'email'.
     * @doc-var     (string) filter[].value - Filter string.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function find()
    {
        // issue an immediate sync
        \Sys::svc('Resque')->addJob('SyncContacts', ['user_id' => \Auth::user()->id]);

        $items = [];

        foreach (\Sys::svc('Contact')->findAllByUserId(\Auth::user()->id, \Input::data('filters') ?:[], \Input::data('sortBy'), \Input::data('sortMode')) as $item)
        {
            $items[] = array
            (
                'id'        => $item->id,
                'name'      => $item->name,
                'email'     => $item->email,
                'muted'     => $item->muted,
                'read'      => $item->read,
                'count'     => $item->count,
                'lastTs'    => $item->last_ts,
            );
        }

        return $items;
    }

    /**
     * Update contact info
     *
     * @doc-var     (int) id!           - Contact ID.
     * @doc-var     (string) name       - Contact name.
     * @doc-var     (bool) muted        - Muted or not.
     * @doc-var     (bool) read         - Read or not.
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
        if (\Input::data('muted') !== null) $contact->muted = \Input::data('muted');
        if (\Input::data('read') !== null) $contact->read = \Input::data('read');

        \Sys::svc('Contact')->update($contact);
    }
}
