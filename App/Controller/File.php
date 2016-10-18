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
     * Finds files related to this chat
     *
     * @doc-var     (int) chatId            - Chat ID.
     * @doc-var     (bool) withImageUrl     - Whether to extract image URL or not.
     *
     * @return mixed
     * @throws \Exception
     */
    static public function findByChatId()
    {
        return [];

        // TODO: t.b.d.

        if (!$chatId = \Input::data('chatId'))
        {
            throw new \Exception('Chat ID not provided.');
        }

        if (!\Sys::svc('Chat')->hasAccess($chatId, \Auth::user()->id))
        {
            throw new \Exception('Access denied.', 403);
        }

        return \Sys::svc('File')->findByChatId($chatId, \Input::data('withImageUrl'));
    }

    /**
     * Download a file
     *
     * @doc-var     (int) messageId         - Message ID.
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

        if (!$offset = \Input::get('offset'))
        {
            throw new \Exception('File offset not provided.');
        }

        try
        {
            if ($message = \Sys::svc('Message')->findByRefIdExtId(\Auth::user()->id, $messageId))
            {
                throw new \Exception('Message not found');
            }
            else
            {
                $inbox = Inbox::init(\Auth::user());
                if (!$messageData = $inbox->getMessage($message->ext_id))
                {
                    throw new \Exception('Cannot retrieve message');
                }

                if (!$file = $messageData['files'][$offset])
                {
                    throw new \Exception('File not found');
                }

                header("Content-type:{$file['type']}");
                header("Content-Disposition:attachment;filename='{$file['name']}'");

                echo $inbox->getFileData($message->ext_id, $offset);
            }
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
