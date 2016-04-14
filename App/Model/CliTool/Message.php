<?php

namespace App\Model\CliTool;

/**
 * Class Message
 * @package App\Model\CliTool
 */
class Message
{
    public function reply($userId, $messageId, $message)
    {
        \Sys::svc('SparkPost')->setupThread($userId, $messageId);
        
        print_r(\Sys::svc('SparkPost')->send([], $message));

        return "\n";
    }
}
