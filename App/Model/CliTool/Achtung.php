<?php

namespace App\Model\CliTool;

/**
 * Class Achtung
 * @package App\Model\CliTool
 */
class Achtung
{
    /**
     * Cleans up all the messages, chats and their links
     *
     * @param int $withUsers
     * @return string
     */
    public function initialState($withUsers = 0)
    {
        $this->justRun('TRUNCATE table chat');
        $this->justRun('TRUNCATE table message');
        $this->justRun('TRUNCATE table chat_user');

        if ($withUsers)
        {
            $this->justRun('DELETE FROM `user` WHERE ext_id IS NULL', []);
            $this->justRun('ALTER TABLE `user` AUTO_INCREMENT = 1', []);
        }

        return "\n";
    }

    /**
     * Clears data for a specified user
     *
     * @param $userId
     * @return string
     */
    public function clearUser($userId)
    {
        // memorize all the chats the user was member in
        $chats = \Sys::svc('Chat')->findAllByUserId($userId);

        $this->justRun('DELETE FROM chat_user WHERE user_id=?', [$userId]);

        foreach ($chats as $chat)
        {
            if (\Sys::svc('Chat')->countUsers($chat->id) < 2)
            {
                // there's only one person left, remove that link too
                $stmt = \DB::prepare('DELETE FROM chat_user WHERE chat_id=? LIMIT 1', [$chat->id]);
                $stmt->execute();
                $stmt->close();

                // kill the chat
                \Sys::svc('Chat')->delete($chat);
            }
        }

        return "Deleted.\n";
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
