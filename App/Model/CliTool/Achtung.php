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
     * Clears data for a specified user
     *
     * @param $userId
     * @return string
     */
    public function clearUser($userId)
    {
        $this->justRun('DELETE FROM chat_user WHERE user_id=?', [$userId]);
        $this->justRun('UPDATE `user` SET last_sync_ts=1 WHERE id=?', [$userId]);

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
