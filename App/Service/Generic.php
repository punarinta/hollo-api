<?php

namespace App\Service;

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
     * @return \StdClass
     * @throws \Exception
     */
    public function create($array)
    {
        $qms = [];
        $keys = [];
        $values = [];

        foreach ($array as $k => $v)
        {
            $qms[] = '?';
            $keys[] = $k;
            $values[] = $v;
        }

        $stmt = \DB::prepare('INSERT INTO `' . $this->class_name . '` (`' . implode('`,`', $keys) . '`) VALUES (' . implode(',', $qms) . ')', $values);
        $stmt->execute();

        $array['id'] = \DB::lastInsertId();
        $stmt->close();

        return \DB::toObject($array);
    }

    /**
     * Updates the object in the database
     *
     * @param $object
     * @return mixed
     * @throws \Exception
     */
    public function update($object)
    {
        $values = [];
        $array = (array) $object;

        $id = $array['id'];
        unset ($array['id']);

        $pairs = [];
        foreach ($array as $k => $v)
        {
            $pairs[] = "`$k`" . '=?';
            $values[] = $v;
        }

        $values[] = $id;

        $stmt = \DB::prepare('UPDATE ' . $this->class_name . ' SET ' . implode(', ', $pairs) . ' WHERE id=? LIMIT 1', $values);
        $stmt->execute();
        $stmt->close();

        return $object;
    }

    /**
     * Deletes an object from the database
     *
     * @param $object
     * @return mixed
     * @throws \Exception
     */
    public function delete($object)
    {
        $stmt = \DB::prepare('DELETE FROM `' . $this->class_name . '` WHERE id=? LIMIT 1', [is_object($object) ? $object->id : $object]);
        $stmt->execute();
        $stmt->close();

        return $object;
    }

    /**
     * Finds the object by its ID
     *
     * @param $id
     * @return null|\StdClass
     */
    public function findById($id)
    {
        return \DB::row('SELECT * FROM `' . $this->class_name . '` WHERE id=? LIMIT 1', [$id]);
    }

    /**
     * Use with care as it may generate tons of data
     *
     * @return array
     */
    public function findAll()
    {
        $all = [];
        $array = \DB::exec($stmt, 'SELECT * FROM `' . $this->class_name . '`');

        while ($stmt->fetch())
        {
            $all[] = \DB::toObject($array);
        }

        return $all;
    }
}