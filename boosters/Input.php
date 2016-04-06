<?php

class Input
{
    /**
     * GET wrapper
     *
     * @param $k
     * @return mixed
     */
    static function get($k)
    {
        return isset ($_GET[$k]) ? $_GET[$k] : null;
    }

    /**
     * Gets a JSON variable from a posted HTTP data.
     *
     * @param $k
     * @return null
     */
    static function json($k)
    {
        if (!isset ($GLOBALS['-P-JSON'])) $GLOBALS['-P-JSON'] = json_decode(file_get_contents('php://input'), 1);

        return Sys::aPath($GLOBALS['-P-JSON'], $k);
    }

    /**
     * Gets a payload parameter.
     *
     * @param $k
     * @return null
     */
    static function data($k)
    {
        return isset ($GLOBALS['-P-JSON']['data']) ? Sys::aPath($GLOBALS['-P-JSON']['data'], $k) : null;
    }
}