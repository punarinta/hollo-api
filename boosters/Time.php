<?php

class Time
{
    /**
     * @param int $time
     * @param bool $full
     * @return bool|string
     */
    static function ts($time = 0, $full = true)
    {
        return date($full ? 'Y-m-d H:i:s' : 'Y-m-d', time() + $time);
    }

    /**
     * Simple and verbose version of ts()
     *
     * @return bool|string
     */
    static function now()
    {
        return date('Y-m-d H:i:s');
    }
}