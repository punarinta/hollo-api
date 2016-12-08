<?php

namespace App\Model;

/*
    Temporary fix for job loss:
    https://github.com/chrisboulton/php-resque/pull/229/files
    Last commit to master was for now on May 13, id df69e8980cc21652f10cd775cb6a0e8c572ffd2d
*/
use \App\Service\Resque as ResqueSvc;

/**
 * Class Worker
 * @package App\Model
 */
class Worker
{
    protected $interval  = 5;
    protected $queueName = 'default';

    /**
     * @param $config
     */
    public function __construct($config)
    {
        $GLOBALS['-CFG'] = $config;

        foreach (glob('App/Jobs/*.php') as $jobFile)
        {
            include_once $jobFile;
        }

        \Resque::setBackend('redis://user:' . $config['redis']['pass'] . '@' . $config['redis']['host'] . ':' . $config['redis']['port']);
    }

    /**
     * Starts listening to the queue
     *
     * @return string
     */
    public function listen()
    {
        // set queue name
        $this->queueName = \Sys::cfg('resque.queue');

        fwrite(STDOUT, 'Starting Worker for queue "' . $this->queueName . '"...');
        $worker = new \Resque_Worker($this->queueName);
        fwrite(STDOUT, " ON DUTY!\n");
        $worker->work($this->interval);

        // THOU SHALT NOT PASS HERETO!
        return "\n[ terminated ]\n";
    }

    /**
     * List all the files in the 'jobs' dir without an exception
     *
     * @return string
     */
    public function listAllJobs()
    {
        $jobList = "\nAvailable jobs:\n";

        foreach (glob('App/Jobs/*.php') as $jobFile)
        {
            $pattern = '/App\/Jobs\/([a-zA-Z]+).php/i';
            $replacement = '$1';
            $jobList .= '* ' . preg_replace($pattern, $replacement, $jobFile) . "\n";
        }

        return $jobList . "\n";
    }

    /**
     * Returns job status
     *
     * @param $jobId
     * @return string
     */
    public function getJobStatus($jobId)
    {
        // pass it to Resque
        $status = new \Resque_Job_Status($jobId);

        $textStatus = 'INVALID TOKEN';
        switch ($status->get())
        {
            case \Resque_Job_Status::STATUS_WAITING:
                $textStatus = 'STATUS_WAITING';
                break;

            case \Resque_Job_Status::STATUS_RUNNING:
                $textStatus = 'STATUS_RUNNING';
                break;

            case \Resque_Job_Status::STATUS_FAILED:
                $textStatus = 'STATUS_FAILED';
                break;

            case \Resque_Job_Status::STATUS_COMPLETE:
                $textStatus = 'STATUS_COMPLETE';
                break;
        }

        return 'Job status: ' . $textStatus . "\n";
    }

    /**
     * Test specified job
     *
     * @param $jobId
     * @return string
     */
    public function testJob($jobId)
    {
        $jobId = '\App\Jobs\\' . $jobId;
        $job = (object) new $jobId;

        $job->testSetup();
        $job->setUp();
        $job->perform();
        $job->tearDown();

        return 'Job test run' . "\n\n";
    }

    /**
     * Runs a job with specified parameters
     *
     * @param $jobId
     * @param $params
     * @return string
     */
    public function runJob($jobId, $params)
    {
        $jobId = '\App\Jobs\\' . $jobId;
        $job = (object) new $jobId;

        if ($params)
        {
            // decode params
            $jParams = json_decode($params, true);

            if (!is_array($jParams))
            {
                return 'Parameters found but were unreadable: ' . $params . "\n\n";
            }

            $job->args = $jParams;
        }

        $job->setUp();
        $job->perform();
        $job->tearDown();

        return "Job run\n\n";
    }

    /**
     * Adds a job for future execution
     *
     * @param $jobId
     * @param $params
     * @return string
     */
    public function pushJob($jobId, $params)
    {
        $jParams = json_decode($params, true);
        if (!is_array($jParams))
        {
            return 'Parameters found but were unreadable: ' . $params . "\n\n";
        }

        $hash = ResqueSvc::addJob($jobId, $jParams);

        return "Job added. Hash = $hash\n";
    }

    /**
     * @return string
     */
    public function stopJob()
    {
        return "TODO: find out how to stop a job\n";
    }

    /**
     * @return string
     */
    public function clearQueue()
    {
        return "TODO: find out how to clear a queue\n";
    }
}
