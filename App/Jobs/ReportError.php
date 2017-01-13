<?php

namespace App\Jobs;

/**
 * Class ReportError
 * Sends an error report to me
 */
class ReportError extends Generic
{
    public function testSetup()
    {
        $this->args = array
        (
            'stack'     => [],
            'msg'       => 'Test',
            'server'    => [],
            'input'     => [],
            'user'      => [],
        );
    }

    /**
     * @return bool
     */
    public function perform()
    {
        $serverData =
        [
            'HTTP_ORIGIN'           => @$this->args['server']['HTTP_ORIGIN'],
            'HTTP_USER_AGENT'       => @$this->args['server']['HTTP_USER_AGENT'],
            'HTTP_CONTENT_TYPE'     => @$this->args['server']['HTTP_CONTENT_TYPE'],
            'HTTP_ACCEPT_ENCODING'  => @$this->args['server']['HTTP_ACCEPT_ENCODING'],
            'HTTP_ACCEPT_LANGUAGE'  => @$this->args['server']['HTTP_ACCEPT_LANGUAGE'],
            'HTTP_X_FORWARDED_FOR'  => @$this->args['server']['HTTP_X_FORWARDED_FOR'],
            'REQUEST_TIME_FLOAT'    => @$this->args['server']['REQUEST_TIME_FLOAT'],
            'HTTP_HOST'             => @$this->args['server']['HTTP_HOST'],
            'HTTP_ACCEPT'           => @$this->args['server']['HTTP_ACCEPT'],
        ];

        $trace = $this->args['stack'];

        foreach ($trace as $k => $v)
        {
            if (isset($trace[$k]['class']) && $trace[$k]['class'] == 'Sys' && $trace[$k]['function'] == 'run')
            {
                $trace[$k]['args'] = '';
            }
        }

        if (isset($this->args['user']['password'])) unset ($this->args['user']['password']);
        if (isset($this->args['input']['data']['credential'])) unset ($this->args['input']['data']['credential']);

        $report  = "<br>\n";
    //    $report .= "worker:\n" . \Sys::cfg('mailless.this_server') . "<br>\n\n";
        $report .= "msg:\n" . $this->args['msg'] . "<br><br>\n\n";
        $report .= "stack:\n" . json_encode($trace) . "<br><br>\n\n";
        $report .= "server:\n" . json_encode($serverData) . "<br><br>\n\n";
        $report .= "input:\n" . json_encode($this->args['input']) . "<br><br>\n\n";
        $report .= "user:\n" . json_encode($this->args['user']) . "<br><br>\n\n";

        mail('vladimir.g.osipov@gmail.com', 'Hollo API error', $report);

        return true;
    }
}
