<?php

namespace App\Model\CliTool;

/**
 * Class Clear
 * @package App\Model\CliTool
 */
class Clear
{
    /**
     * Remove fake users and all their belongings from the system
     *
     * @return string
     */
    public function users()
    {
        $count = 0;

        foreach (\Sys::svc('User')->findAll() as $user)
        {
            if (filter_var($user->email, FILTER_VALIDATE_EMAIL) === false)
            {
                echo "{$user->email}\n";

                // 1. Delete user
                \Sys::svc('User')->delete($user);

                // 2. Delete all his messages
                $this->justRun('DELETE FROM message WHERE user_id=?', [$user->id]);

                // 3. Delete all his chat_user links
                foreach (\Sys::svc('Chat')->findAllByUserId($user->id) as $chat)
                {
                    \Sys::svc('Chat')->dropUser($chat->id, $user->id);
                }

                ++$count;
            }
        }

        return "Total removed = $count\n";
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
