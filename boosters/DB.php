<?php

class DB
{
    static $pageStart = null;
    static $pageLength = null;

    /**
     * Connects to the database using the parameters from the config
     */
    static function connect()
    {
        $c = $GLOBALS['-CFG']['db'];
        if (!$GLOBALS['-DB-L'] = @mysqli_connect($c['host'], $c['user'], $c['pass'], $c['database'], $c['port']))
        {
            throw new \Exception('Cannot connect to the DB');
        }

        mysqli_set_charset($GLOBALS['-DB-L'], 'utf8mb4');
    }

    /**
     * Closes DB connection
     */
    static function disconnect()
    {
        mysqli_close($GLOBALS['-DB-L']);
    }

    /**
     * @return mixed
     */
    static function lastInsertId()
    {
        return $GLOBALS['-DB-L']->insert_id;
    }

    /**
     * Returns a connection link
     *
     * @return mixed
     */
    static function l()
    {
        return $GLOBALS['-DB-L'];
    }

    /**
     * Starts a transaction
     */
    static function begin()
    {
        mysqli_autocommit($GLOBALS['-DB-L'], false);
    }

    /**
     * Ends a transaction with a commit
     */
    static function commit()
    {
        mysqli_commit($GLOBALS['-DB-L']);
        mysqli_autocommit($GLOBALS['-DB-L'], true);
    }

    /**
     * Ends a transaction with a rollback
     */
    static function rollback()
    {
        mysqli_rollback($GLOBALS['-DB-L']);
        mysqli_autocommit($GLOBALS['-DB-L'], true);
    }

    /**
     * @param $q
     * @param null $params
     * @return mixed
     * @throws Exception
     */
    static function prepare($q, $params = null)
    {
        $stmt = $GLOBALS['-DB-L']->prepare($q);

        if ($stmt === false)
        {
            throw new \Exception('Invalid statement: ' . $q);
        }

        if ($params)
        {
            $parameters = [str_repeat('s', count($params))];
            foreach ($params as $key => &$value) $parameters[] = &$value;
            call_user_func_array(array($stmt, 'bind_param'), $parameters);
        }

        return $stmt;
    }

    /**
     * @param $array
     * @return \StdClass
     */
    static function toObject($array)
    {
        $obj = new \StdClass();

        foreach ($array as $k => $v) $obj->{$k} = $v;

        return $obj;
    }

    /**
     * Full cycle to get 1 row
     *
     * @param $q
     * @param null $params
     * @return null|StdClass
     * @throws Exception
     */
    static function row($q, $params = null)
    {
//        $GLOBALS['-DBG-SQL'][] = [$q, $params];

        $stmt = self::prepare($q, $params);
        $stmt->execute();
        $stmt->store_result();

        if (!$stmt->num_rows)
        {
            $stmt->close();
            return null;
        }

        $row = self::stmtBindAssoc($stmt);
        $stmt->fetch();
        $stmt->close();

        return self::toObject($row);
    }

    /**
     * Full cycle to get all the rows with pagination
     *
     * @param $q
     * @param null $params
     * @return array
     */
    static function rows($q, $params = null)
    {
        $array = \DB::exec($stmt, $q . self::sqlPaging(), $params);
        $rows = [];

        while ($stmt->fetch())
        {
            $rows[] = self::toObject($array);
        }

        $stmt->close();

        return $rows;
    }

    /**
     * Generates an SQL paging.
     *
     * @return string
     */
    static function sqlPaging()
    {
        if (\DB::$pageStart !== null)
        {
            $pageLength = \DB::$pageLength ? \DB::$pageLength : 25;
            return ' LIMIT ' . $pageLength . ' OFFSET ' . \DB::$pageStart;
        }

        return '';
    }

    /**
     * @param $stmt
     * @param $q
     * @param null $params
     * @return array
     * @throws Exception
     */
    static function exec(&$stmt, $q, $params = null)
    {
//        $GLOBALS['-DBG-SQL'][] = [$q, $params];

        $stmt = self::prepare($q, $params);
        $stmt->execute();
        $stmt->store_result();

        return self::stmtBindAssoc($stmt);
    }

    /**
     * @param $stmt
     * @return array
     */
    static function stmtBindAssoc(&$stmt)
    {
        $count  = 1;
        $out    = [];
        $fields = [$stmt];

        $data = mysqli_stmt_result_metadata($stmt);

        while ($field = mysqli_fetch_field($data))
        {
            $fields[$count] = &$out[$field->name];
            ++$count;
        }
        call_user_func_array('mysqli_stmt_bind_result', $fields);

        return $out;
    }
}
