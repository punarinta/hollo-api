<?php

namespace App\Model\FileParser;

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

        $text = str_replace("\n ", '', $text);

        foreach (explode("\n", $text) as $row)
        {
            $split = explode(':', trim($row), 2);

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
                    $title = $split[1];
                    break;

                case 'DESCRIPTION':
                    $description = $split[1];
                    break;

                case 'UID':
                    $uid = $split[1];
                    break;

                default:
                    $split = explode(';', trim($row), 2);

                    switch ($split[0])
                    {
                        case 'ORGANIZER':
                            $timeStart = $split[1];
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
