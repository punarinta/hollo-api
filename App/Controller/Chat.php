<?php

namespace App\Controller;

/**
 * Class Chat
 * @package App\Controller
 * @doc-api-path /api/chat
 */
class Chat extends Generic
{
    static public function find()
    {
        // issue an immediate sync
    //    \Sys::svc('Resque')->addJob('SyncContacts', ['user_id' => \Auth::user()->id]);

        $items = [];

        foreach (\Sys::svc('Chat')->findAllByUserId(\Auth::user()->id, \Input::data('filters') ?:[], \Input::data('sortBy'), \Input::data('sortMode')) as $chat)
        {
            // get flags
            $flags = \Sys::svc('Chat')->getFlags($chat->id, \Auth::user()->id);

            $items[] = array
            (
                'id'        => $chat->id,
                'name'      => $chat->name,
                'muted'     => $flags->muted,
                'read'      => $flags->read,
                'count'     => $chat->count,
                'lastTs'    => $chat->last_ts,
                'users'     => \Sys::svc('User')->findByChatId($chat->id, true),
            );
        }

        return $items;
    }

    /**
     * Adds a Chat with emails
     *
     * @return mixed
     * @throws \Exception
     */
    static public function add()
    {
        if (!$emails = \Input::data('emails'))
        {
            throw new \Exception('No emails provided.');
        }

        // check just in case
        if ($chat = \Sys::svc('Chat')->findByEmails($emails))
        {
            return $chat;
        }

        \DB::begin();

        try
        {
            // create chat itself
            $chat = \Sys::svc('Chat')->create(array
            (
                'name'      => null,
                'count'     => 0,
                'muted'     => 0,
                'read'      => 1,
                'last_ts'   => 0,
            ));

            $userIds = [];
            // assure that all the users exist
            foreach ($emails as $email)
            {
                if (!$user = \Sys::svc('User')->findByEmail($email))
                {
                    // create a dummy user
                    $user = \Sys::svc('User')->create(array
                    (
                        'email'     => $email,
                        'ext_id'    => null,
                        'roles'     => \Auth::USER,
                        'created'   => time(),
                        'settings'  => '',
                    ));
                }

                $userIds[] = $user->id;
            }

            // link users into the chat
            foreach ($userIds as $userId)
            {
                $stmt = \DB::prepare('INSERT INTO chat_user (`chat_id`, `user_id`) VALUES (?,?)', [$chat->id, $userId]);
                $stmt->execute();
                $stmt->close();
            }

            \DB::commit();
        }
        catch (\Exception $e)
        {
            \DB::rollback();
            throw $e;
        }

        return $chat;
    }

    /**
     * Updates Chat info
     *
     * @doc-var     (int) id!           - Chat ID.
     * @doc-var     (string) name       - Chat name.
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

        if (!$chat = \Sys::svc('Chat')->findByIdAndUserId($id, \Auth::user()->id))
        {
            throw new \Exception('Chat not found.');
        }

        if ($name = trim(\Input::data('name')))
        {
            $chat->name = $name;
        }

        if (\Input::data('muted') !== null)
        {
            $chat->muted = \Input::data('muted');
        }

        if (\Input::data('read') !== null)
        {
            $chat->read = \Input::data('read');
        }

        \Sys::svc('Chat')->update($chat);
    }

    /**
     * Deletes a contact
     *
     * @doc-var     (int) id!           - Contact ID.
     *
     * @throws \Exception
     */
    static public function delete()
    {
        if (!$id = \Input::data('id'))
        {
            throw new \Exception('No contact ID provided.');
        }

        if (!$contact = \Sys::svc('Contact')->findByIdAndUserId($id, \Auth::user()->id))
        {
            throw new \Exception('Contact not found.');
        }

        foreach (\Sys::svc('Message')->findByContactId($contact->id) as $message)
        {
            // kill message and its links
            \Sys::svc('Message')->delete($message);
            $stmt = \DB::prepare('DELETE FROM contact_message WHERE contact_id=? AND message_id=?', [$contact->id, $message->id]);
            $stmt->execute();
            $stmt->close();
        }

        // kill contact
        \Sys::svc('Contact')->delete($contact);
    }
}
