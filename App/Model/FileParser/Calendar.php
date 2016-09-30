<?php

namespace App\FileParse\Model;

/**
 * ICS calendar file parser
 *
 * Class Calendar
 * @package App\FileParse\Model
 */
class Calendar
{
    /**
     * Gets information from an ICS calendar file
     *
     * @param $text
     * @return array
     */
    static public function parse($text)
    {
        $uid = '';
        $where = '';
        $title = '';
        $timeEnd = 0;
        $timeStart = 0;
        $description = '';

        foreach (explode("\n", $text) as $row)
        {
            $split = explode(':', $row);

            switch ($split[0])
            {
                case 'DTSTART':
                    $timeStart = strtotime($split[1]);
                    break;

                case 'DTEND':
                    $timeEnd = strtotime($split[1]);
                    break;

                case 'LOCATION':
                    $where = strtotime($split[1]);
                    break;

                case 'SUMMARY':
                    $title = strtotime($split[1]);
                    break;

                case 'DESCRIPTION':
                    $description = strtotime($split[1]);
                    break;

                case 'UID':
                    $uid = strtotime($split[1]);
                    break;

                default:
                    $split = explode(';', $row);

                    switch ($split[0])
                    {
                        case 'ORGANIZER':
                            $timeStart = strtotime($split[1]);
                            break;

                    }
            }
        }

        return array
        (
            'from'  => $timeStart,
            'to'    => $timeEnd,
            'where' => $where,
            'title' => $title,
            'descr' => $description,
            'uid'   => $uid,
        );
    }
}
