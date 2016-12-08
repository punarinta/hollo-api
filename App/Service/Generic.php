<?php

namespace App\Service;

use MongoDB\BSON\ObjectID;
use \MongoDB\Driver\BulkWrite;

class Generic
{
    protected static $class_name;

    /**
     * Creates an object in the database
     *
     * @param $array
     * @return mixed
     */
    public static function create($array)
    {
        $bulk = new BulkWrite();
        $document = new \stdClass();
        $array['_id'] = $bulk->insert($array)->__toString();
        $GLOBALS['-DB-L']->executeBulkWrite('hollo.' . static::$class_name, $bulk);

        foreach ($array as $k => $v)
        {
            $document->$k = $v;
        }

        return $document;
    }

    /**
     * Updates the object in the database
     *
     * @param object $document
     * @param array $set
     */
    public static function update($document, $set = [])
    {
        $bulk = new BulkWrite();
        $id = new ObjectID($document->_id);
        $tempId = $document->_id;
        unset ($document->_id);

        $bulk->update(['_id' => $id], $set ? ['$set' => $set] : $document);
        $GLOBALS['-DB-L']->executeBulkWrite('hollo.' . static::$class_name, $bulk);
        $document->_id = $tempId;
    }

    /**
     * Deletes an object from the database
     *
     * @param object $document
     * @return bool
     */
    public static function delete($document)
    {
        if (!$document || !$document->_id)
        {
            return false;
        }

        $bulk = new BulkWrite();
        $id = new ObjectID($document->_id);

        $bulk->delete(['_id' => $id], ['limit' => 1]);
        $GLOBALS['-DB-L']->executeBulkWrite('hollo.' . static::$class_name, $bulk);

        return true;
    }

    /**
     * Finds the object by its ID
     *
     * @param array $filter
     * @param array $options
     * @return null|mixed
     */
    public static function findOne($filter = [], $options = [])
    {
        $rows = \DB::query(static::$class_name, $filter, $options);

        foreach ($rows as $row)
        {
            if (is_object($row->_id))
            {
                $row->_id = $row->_id->__toString();
            }

            return $row;
        }

        return null;
    }

    /**
     * Use with care as it may generate tons of data
     *
     * @param array $filter
     * @param array $options
     * @return array
     */
    public static function findAll($filter = [], $options = [])
    {
        $rows = [];

        foreach (\DB::query(static::$class_name, $filter, $options) as $row)
        {
            if (is_object($row->_id))
            {
                $row->_id = $row->_id->__toString();
            }

            $rows[] = $row;
        }

        return $rows;
    }
}