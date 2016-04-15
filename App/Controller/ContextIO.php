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
        // TODO: possibly add athentication — https://context.io/docs/2.0/accounts/webhooks#callbacks

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            throw new \Exception('Wrong HTTP method.', 405);
        }

        if (!$messageExtId = \Input::json('message_data.message_id'))
        {
            // probably this was a call to 'failure_notif_url'
            return false;
        }
        
        // sync message
        \Sys::svc('Message')->sync(\Input::json('account_id'), $messageExtId);

        return true;
    }
}
