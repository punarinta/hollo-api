<?php

namespace App\Model\CliTool;

/**
 * Class Achtung
 * @package App\Model\CliTool
 */
class Achtung
{
    /**
     * Cleans up all the messages, contacts and their links
     *
     * @return string
     */
    public function initialState()
    {
        $this->justRun('TRUNCATE table chat');
        $this->justRun('TRUNCATE table message');
        $this->justRun('TRUNCATE table chat_user');
        $this->justRun('UPDATE `user` SET last_sync_ts=1');

        return "\n";
    }

    /**
     * Clear contact/message data for a specified user
     *
     * @param $userId
     * @return string
     */
    public function clearUser($userId)
    {
        $messageCount = 0;
        $contactCount = 0;

        foreach (\Sys::svc('Contact')->findAllByUserId($userId) as $contact)
        {
            echo "Clearing contact {$contact->email}\n";

            foreach (\Sys::svc('Message')->findByContactId($contact->id) as $message)
            {
                // kill message and its links
                \Sys::svc('Message')->delete($message);
                $this->justRun('DELETE FROM contact_message WHERE contact_id=? OR message_id=?', [$contact->id, $message->id]);
                ++$messageCount;
            }

            // kill contact
            \Sys::svc('Contact')->delete($contact);
            ++$contactCount;
        }

        echo "Delected $contactCount contacts and $messageCount messages.\n";

        $this->justRun('ALTER TABLE contact AUTO_INCREMENT = 1');
        $this->justRun('ALTER TABLE message AUTO_INCREMENT = 1');
        $this->justRun('UPDATE user SET last_sync_ts=1 WHERE id=?', [$userId]);

        return "\n";
    }

    /**
     * @param $sql
     * @param array $params
     * @throws \Exception
     */
    private function justRun($sql, $params = [])
    {
        $stmt = \DB::prepare($sql, $params);
        $stmt->execute();
        $stmt->close();
    }
}
