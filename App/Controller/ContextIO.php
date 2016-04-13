<?php

namespace App\Controller;

/**
 * Class ContextIO
 * @package App\Controller
 * @doc-api-path /api/context-io
 */
class ContextIO
{
    /**
     * Inbound endpoint for context.io
     */
    static public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            throw new \Exception('Wrong HTTP method.', 405);
        }

        if (!$messageExtId = \Input::json('message_data.message_id'))
        {
            throw new \Exception('Message ext ID not found.');
        }

        
    }
}
