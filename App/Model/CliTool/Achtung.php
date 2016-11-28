<?php

namespace App\Model\CliTool;
use \MongoDB\Driver\BulkWrite;

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
        // truncate chats
        try
        {
            \DB::command(['drop' => 'chat']);
        }
        catch (\Exception $e)
        {
            echo "Chats were already dropped\n";
        }

        if ($withUsers)
        {
            $bulk = new BulkWrite();
            $bulk->delete(['roles' => ['$exists' => false]]);
            $GLOBALS['-DB-L']->executeBulkWrite('hollo.user', $bulk);
        }

        return "\n";
    }
}
