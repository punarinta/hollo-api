<?php

namespace App\Service;

use MongoDB\BSON\ObjectID;
use \MongoDB\Driver\BulkWrite;

class Generic
{
    protected $ClassName;
    protected $class_name;

    public function __construct()
    {
        // get table name out from class name
        $names = explode('\\', get_class($this));
        $this->ClassName  = end($names);
        $this->class_name = strtolower(preg_replace_callback('/(^|[a-z])([A-Z])/', function ($matches)
        {
            return strtolower(strlen($matches[1]) ? $matches[1] . '_' . $matches[2] : $matches[2]);
        }, $this->ClassName));
    }

    /**
     * Creates an object in the database
     *
     * @param $array
     * @return mixed
     */
    public function create($array)
    {
        $bulk = new BulkWrite();
        $document = new \stdClass();
        $array['_id'] = $bulk->insert($array)->__toString();
        $GLOBALS['-DB-L']->executeBulkWrite('hollo.' . $this->class_name, $bulk);

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
    public function update($document, $set = [])
    {
        $bulk = new BulkWrite();
        $id = new ObjectID($document->_id);
        $tempId = $document->_id;
        unset ($document->_id);

        $bulk->update(['_id' => $id], $set ? ['$set' => $set] : $document);
        $GLOBALS['-DB-L']->executeBulkWrite('hollo.' . $this->class_name, $bulk);
        $document->_id = $tempId;
    }

    /**
     * Deletes an object from the database
     *
     * @param object $document
     * @return bool
     */
    public function delete($document)
    {
        if (!$document || !$document->_id)
        {
            return false;
        }

        $bulk = new BulkWrite();
        $id = new ObjectID($document->_id);

        $bulk->delete(['_id' => $id], ['limit' => 1]);
        $GLOBALS['-DB-L']->executeBulkWrite('hollo.' . $this->class_name, $bulk);

        return true;
    }

    /**
     * Finds the object by its ID
     *
     * @param array $filter
     * @param array $options
     * @return null|mixed
     */
    public function findOne($filter = [], $options = [])
    {
        $rows = \DB::query($this->class_name, $filter, $options);

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
    public function findAll($filter = [], $options = [])
    {
        $rows = [];

        foreach (\DB::query($this->class_name, $filter, $options) as $row)
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