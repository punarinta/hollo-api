<?php

namespace App\Service;

class Resque
{
    protected static $connected = false;

    /**
     * Checks if you are connected to Redis server
     *
     * @return bool
     */
    public static function checkConnection()
    {
        if (!self::$connected)
        {
            include_once 'vendor/colinmollenhour/credis/Client.php';
            
            // Get settings
            $config = \Sys::cfg('redis');

            // Check if Redis is running
            if (!is_resource($conn = @fsockopen($config['host'], $config['port'], $a, $b, 5)))
            {
                return false;
            }

            fclose($conn);

            // set v-verbosity on
            putenv('VVERBOSE=1');

            // Connect to Redis
            \Resque::setBackend('redis://user:' . $config['pass'] . '@' . $config['host'] . ':' . $config['port']);
            self::$connected = true;
        }

        return true;
    }

    /**
     * Add job to a queue.
     *
     * @param $job              - should correspond to a class name
     * @param $params
     * @return null|string
     */
    public static function addJob($job, $params)
    {
        if (self::checkConnection())
        {
            return \Resque::enqueue(\Sys::cfg('resque.queue'), '\App\Jobs\\' . $job, $params, true);
        }
        else
        {
            $fp = fopen('data/files/resque_failure.log', 'a+');
            fputs($fp, date('c') . ": no connection\n");
            fclose($fp);
            return null;
        }
    }

    /**
     * Check a job
     *
     * @param string $token
     * @return string
     **/
    public static function checkJob($token)
    {
        return self::checkConnection() ? new \Resque_Job_Status($token) : null;
    }

    /**
     * Run a job directly without a worker
     *
     * @param $job
     * @param $params
     * @return mixed
     */
    public static function execute($job, $params)
    {
        $className = '\App\Jobs\\' . $job;
        $object = (object) new $className;
        $object->args = $params;
        return $object->perform();
    }
}