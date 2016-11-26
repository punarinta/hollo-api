<?php

use \MongoDB\Driver\Manager;
use \MongoDB\Driver\Query;

class DB
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
        if (self::$pageLength)
        {
            $options['skip'] = self::$pageStart;
            $options['limit'] = self::$pageLength ?: 25;
        }

        $query = new Query($filter, $options);

        return $GLOBALS['-DB-L']->executeQuery('hollo.' . $collection, $query);
    }

    static function max($collection, $field)
    {
        $query = new Query([], ['sort' => [$field => -1], 'limit' => 1, 'projection' => [$field => 1]]);
        $res = $GLOBALS['-DB-L']->executeQuery('hollo.' . $collection, $query)->toArray();

        return $res[0]->{$field};
    }
}
