<?php

namespace App\Jobs;

/**
 * Class Generic
 */
class Generic
{
    public $args = [];

    /**
     * used for self-testing
     */
    public function testSetup()
    {
        echo "No test setup found within the job\n\n";
        exit;
    }

    public function setUp()
    {
        // Get application settings
        $redisConfig = \Sys::cfg('redis');

        \DB::connect();

        $GLOBALS['-SYS-VERBOSE'] = true;

        // Setup and authenticate redis for child-job
        \Resque::setBackend('redis://user:' . $redisConfig['pass'] . '@' . $redisConfig['host'] . ':' . $redisConfig['port']);
    }

    public function tearDown()
    {
        \DB::disconnect();
    }
}
