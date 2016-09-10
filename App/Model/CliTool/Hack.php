<?php

namespace App\Model\CliTool;
use App\Model\ContextIO\ContextIO;

/**
 * Class Hack
 * @package App\Model\CliTool
 */
class Hack
{
    public function getFileLink($accountId, $fileExtId)
    {
        $cfg = \Sys::cfg('contextio');
        $conn = new ContextIO($cfg['key'], $cfg['secret']);

        return $conn->getFileContent($accountId,
        [
            'file_id' => $fileExtId,
            'as_link' => 1,
        ]);
    }
}
