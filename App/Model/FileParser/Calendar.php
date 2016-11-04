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
        $organizer = '';
        $attendees = [];
        $description = '';

        $text = preg_replace("/[\n|\r]{1,}[ ]/", '', $text);

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

                case 'ORGANIZER':
                    $organizer = self::getPerson($split[1]);
                    break;

                default:
                    $split = explode(';', trim($row), 2);

                    switch ($split[0])
                    {
                        case 'ORGANIZER':
                            $organizer = self::getPerson($split[1]);
                            break;

                        case 'ATTENDEE':
                            $attendees[] = self::getPerson($split[1]);
                            break;

                        case 'SUMMARY':
                            $title = explode(':', trim($split[1]));
                            $title = end($title);
                            break;

                        case 'LOCATION':
                            $where = explode(':', trim($split[1]));
                            $where = end($where);
                            break;

                        case 'DTSTART':
                            foreach (explode(':', trim($split[1])) as $item)
                            {
                                if ($timeStart = strtotime($item))
                                {
                                    break;
                                }
                            }
                            break;

                        case 'DTEND':
                            foreach (explode(':', trim($split[1])) as $item)
                            {
                                if ($timeEnd = strtotime($item))
                                {
                                    break;
                                }
                            }
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
            'org'   => $organizer,
            'att'   => $attendees,
        );
    }

    /**
     * @param $line
     * @return bool|mixed
     */
    static public function getPerson($line)
    {
        $email = '';
        $name = '';
        $keys = [];

        $items1 = explode(';', $line);
        foreach ($items1 as $item1)
        {
            $items2 = explode(':', $item1);
            foreach ($items2 as $item2)
            {
                $keys[] = $item2;
            }
        }

        foreach ($keys as $k => $v)
        {
            if (stripos($v, 'CN=') !== false)
            {
                $name = explode('=', $v);
                $name = trim(@$name[1]);
            }

            $v = strtolower($v);

            if ($v == 'mailto')
            {
                $email = $keys[$k+1];
            }
        }

        return [$email, $name];
    }
}
