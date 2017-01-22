<?php

namespace App\Model\CliTool;

use App\Model\Inbox\Inbox;
use App\Service\Chat as ChatSvc;
use App\Service\File as FileSvc;
use MongoDB\Driver\BulkWrite;

/**
 * Class Once
 * @package App\Model\CliTool
 */
class Once
{
    /**
     * Populate 'muted'
     *
     * @param string $fileName
     */
    public function importMuted($fileName = 'populate/muted.json')
    {
        $bulk = new BulkWrite();
        $elements = json_decode(file_get_contents($fileName));

        foreach ($elements as $element)
        {
            if (!$element->user) unset ($element->user);
            $bulk->insert($element);
        }

        $GLOBALS['-DB-L']->executeBulkWrite('hollo.muted', $bulk);
    }

    /**
     * Populate 'mail_service'
     *
     * @param string $fileName
     */
    public function importServices($fileName = 'populate/mail_service.json')
    {
        $bulk = new BulkWrite();
        $elements = json_decode(file_get_contents($fileName));

        foreach ($elements as $element)
        {
            $bulk->insert(array
            (
                'name'      => $element->name,
                'domains'   => explode('|', trim($element->domains, '|')),
                'cfgIn'     => json_decode($element->cfg_in),
                'cfgOut'    => json_decode($element->cfg_out),
            ));
        }

        $GLOBALS['-DB-L']->executeBulkWrite('hollo.mail_service', $bulk);
    }

    /**
     * Populate 'user'
     *
     * @param string $fileName
     */
    public function importUsers($fileName = 'populate/user.json')
    {
        // TODO: before running set 'svc' and camelCase flags

        $bulk = new BulkWrite();
        $elements = json_decode(file_get_contents($fileName));

        foreach ($elements as $element)
        {
            $array = array
            (
                'email'      => $element->email,
                'roles'      => $element->roles,
                'settings'   => json_decode($element->settings),
                'lastMuid'   => $element->last_muid,
            );

            if ($element->name) $array['name'] = $element->name;

            $bulk->insert($array);
        }

        $GLOBALS['-DB-L']->executeBulkWrite('hollo.user', $bulk);
    }

    /**
     * @return string
     */
    public function createThumbnails()
    {
        $count = 0;

        foreach (ChatSvc::findAll() as $chat)
        {
            echo "Chat {$chat->_id}...\n";

            foreach ($chat->messages ?? [] as $message)
            {
                if (!@$message->extId) continue;
                if (!@$message->files) continue;

                $imapObject = Inbox::init($message->refId);
                $messageData = $imapObject->getMessage($message->extId);

                $fileCount = 0;
                foreach ($message->files ?? [] as $file)
                {
                    if (FileSvc::createAttachmentPreview($imapObject, $messageData, $chat->_id, $message->id, $fileCount, $file->type))
                    {
                        ++$count;
                    }

                    ++$fileCount;
                }
            }
        }

        return "Total files created: $count\n";
    }
}
