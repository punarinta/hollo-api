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
        $this->justRun('TRUNCATE table contact');
        $this->justRun('TRUNCATE table message');
        $this->justRun('TRUNCATE table contact_message');

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
            foreach (\Sys::svc('Message')->findByContactEmail($contact->email, $userId) as $message)
            {
                // kill message and its links
                \Sys::svc('Message')->delete($message);
                $this->justRun('DELETE FROM contact_message WHERE contact_id=? AND message_id=?', [$contact->id, $message->id]);
                ++$messageCount;
            }

            // kill contact
            \Sys::svc('Contact')->delete($contact);
            ++$contactCount;
        }

        echo "Delected $contactCount contacts and $messageCount messages.\n";

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
