<?php

class Time
{
    /**
     * This function is needed for PoEdit to fetch translations
     */
    static function __translation_strings()
    {
        \Lang::translatePlural('year', 'years', 5);
        \Lang::translatePlural('month', 'months', 5);
        \Lang::translatePlural('week', 'weeks', 5);
        \Lang::translatePlural('day', 'days', 5);
        \Lang::translatePlural('hour', 'hours', 5);
        \Lang::translatePlural('minute', 'minutes', 5);
        \Lang::translatePlural('second', 'seconds', 5);
    }

    /**
     * Shows time left
     *
     * @param $diff
     * @return string
     */
    static function timeLeft($diff)
    {
        if ($diff === 0)
        {
            return \Lang::translate('just now');
        }

        $intervals = array
        (
            1                   => ['year',    31536000],  // 365 days
            $diff < 31556926    => ['month',   2592000],   // 30 days
            $diff < 2592000     => ['week',    604800],
            $diff < 604800      => ['day',     86400],
            $diff < 86400       => ['hour',    3600],
            $diff < 3600        => ['minute',  60],
            $diff < 60          => ['second',  1]
        );

        $value = floor($diff / $intervals[1][1]);

        return $value . ' ' . \Lang::translatePlural($intervals[1][0], $intervals[1][0] . 's', $value);
    }

    /**
     * Outputs text like "X time passed"
     *
     * @param $date
     * @return string
     */
    static function timePassed($date)
    {
        if ($date === '0000-00-00 00:00:00')
        {
            return \Lang::translate('not active');
        }

        return self::timeLeft(time() - strtotime($date)) . ' ' . \Lang::translate('ago');
    }

    /**
     * Outputs text with expiration time
     *
     * @param $expiryDate
     * @return string
     */
    static function daysLeft($expiryDate)
    {
        if (is_int($expiryDate))
        {
            $expiryTs = time() + $expiryDate;
        }
        else
        {
            $expiryTs = strtotime($expiryDate);
        }

        if ($expiryDate === '0000-00-00')
        {
            return strtolower(\Lang::translate('Never expires'));
        }

        $daysLeft = round(($expiryTs - time()) / 86400);

        if ($daysLeft <= 0)
        {
            return \Lang::translate('expired');
        }
        if ($daysLeft <= 1)
        {
            return \Lang::translate('last day');
        }

        return round(($expiryTs - time()) / 86400) . \Lang::translate('days left');
    }

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