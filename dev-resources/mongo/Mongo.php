<?php

use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

class Mongo
{
    static $pageStart = null;
    static $pageLength = null;

    /**
     * Connects to the database using the parameters from the config
     */
    static function connect()
    {
        $GLOBALS['-DB-L'] = new Manager('mongodb://127.0.0.1:27017/hollo');
    }

    /**
     * @param $collection
     * @param array $filter
     * @param array $options
     * @return mixed
     */
    static function query($collection, $filter = [], $options = [])
    {
        $query = new Query($filter, $options);

        return $GLOBALS['-DB-L']->executeQuery('hollo.' . $collection, $query);
    }
}
