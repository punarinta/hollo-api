<?php

namespace App\Model\CliTool;

use App\Service\Chat as ChatSvc;

/**
 * Class Stats
 * @package App\Model\CliTool
 */
class Stats
{
    public function mimeUsage()
    {
        $mime = [];

        foreach (ChatSvc::findAll() as $chat)
        {
            foreach ($chat->messages ?? [] as $message)
            {
                if (!@$message->files) continue;

                foreach ($message->files ?? [] as $file)
                {
                    if (!isset ($mime[$file->type])) $mime[$file->type] = 0;
                    $mime[$file->type]++;
                }
            }
        }

        asort($mime);

        print_r($mime);
    }
}