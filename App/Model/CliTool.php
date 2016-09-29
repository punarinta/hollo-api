<?php

namespace App\Model;

/**
 * Class CliTool
 * @package App\Model
 */
class CliTool
{
    /**
     * Constructor
     *
     * @param $config
     */
    public function __construct($config)
    {
        $GLOBALS['-CFG'] = $config;
        \DB::connect();
    //    \Resque::setBackend('redis://user:' . $config['redis']['pass'] . '@' . $config['redis']['host'] . ':' . $config['redis']['port']);
    }

    /**
     * Parses and executes CLI command
     *
     * @param $argv
     * @return string
     */
    public function dispatch($argv)
    {
        $toolName = '\App\Model\CliTool\\' . ucfirst($argv[1]);

        if (!class_exists($toolName))
        {
            array_shift($argv);
            return "Unknown command '" . implode(' ', $argv) . "'. No class found: '$toolName'.";
        }

        $tool = new $toolName;
        $method = isset ($argv[2]) ? $argv[2] : 'index';

        if (!method_exists($tool, $method))
        {
            array_shift($argv);
            return "Unknown command '" . implode(' ', $argv) . "'. No method found: '$method'.";
        }

        $GLOBALS['-SYS-VERBOSE'] = true;

        return call_user_func_array([$tool, $method], array_splice($argv, 3));
    }
}
