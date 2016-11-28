<?php

namespace App\Controller;
use App\Model\Inbox\Inbox;

/**
 * Class File
 * @package App\Controller
 * @doc-api-path /api/file
 */
class File extends Generic
{
    /**
     * Download a file
     *
     * @doc-var     (string) messageId      - Message ID.
     * @doc-var     (int) offset            - File offset in the message.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function download()
    {
        if (!$messageId = \Input::get('messageId'))
        {
            throw new \Exception('Message ID not provided.');
        }

        if (($offset = \Input::get('offset') === null))
        {
            throw new \Exception('File offset not provided.');
        }

        try
        {
            if (!$chat = \Sys::svc('Chat')->findOne(['messages.id' => $messageId]))
            {
                throw new \Exception('Message not found');
            }

            if (!\Sys::svc('Chat')->hasAccess($chat, \Auth::user()->_id))
            {
                throw new \Exception('Access denied.', 403);
            }

            $message = null;
            foreach ($chat->messages as $messageRow)
            {
                if ($messageRow->id == $messageId)
                {
                    $message = $messageRow;
                    break;
                }
            }

            $inbox = Inbox::init($message->refId);

            if (!$messageData = $inbox->getMessage($message->extId))
            {
                throw new \Exception('Cannot retrieve message');
            }
            if (!$file = $messageData['files'][$offset])
            {
                throw new \Exception('File not found');
            }

            header("Content-type:{$file['type']}");
            header("Content-Disposition:attachment;filename='{$file['name']}'");

            echo $inbox->getFileData($message->extId, $offset);
        }
        catch (\Exception $e)
        {
            http_response_code(404);
            echo $e->getMessage();
        }
        finally
        {
            exit;
        }
    }
}
