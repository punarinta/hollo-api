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

    public function duplicateMessages()
    {
        $messages = \Sys::svc('Message')->findAll();
        $len = count($messages);
        $count = 0;

        for ($i = 0; $i < $len; ++$i)
        {
            for ($j = $i + 1; $j < $len; ++$j)
            {
                if ($messages[$i]->chat_id == $messages[$j]->chat_id
                &&  $messages[$i]->user_id == $messages[$j]->user_id
                &&  $messages[$i]->body == $messages[$j]->body
                &&  abs($messages[$i]->ts - $messages[$j]->ts) < 60)
                {
                    echo "{$messages[$i]->id} ({$messages[$i]->ext_id})  :  {$messages[$j]->id} ({$messages[$j]->ext_id})\n";
                    ++$count;
                }
            }
        }

        echo "Count = $count\n";
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
