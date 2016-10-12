<?php

namespace App\Model\Inbox;

/**
 * Class Generic
 * @package App\Model
 */
class Generic
{
    /**
     * @param $str
     * @return string
     */
    protected function base64_decode($str)
    {
        return base64_decode(strtr($str, ['-' => '+', '_' => '/']));
    }

    /**
     * @param $headers
     * @return array
     */
    protected function getAddresses($headers)
    {
        $addr = [];

        foreach ($headers as $k => $v)
        {
            if ($k == 'From')
            {
                $addr['from'] = $this->splitAddress($v[0]);
            }
            if ($k == 'To')
            {
                if (!isset ($addr['to'])) $addr['to'] = [];
                $addr['to'][] = $this->splitAddress($v[0]);
            }
            if ($k == 'Cc')
            {
                if (!isset ($addr['cc'])) $addr['cc'] = [];
                $addr['cc'][] = $this->splitAddress($v[0]);
            }
        }

        return $addr;
    }

    /**
     * @param $str
     * @return array
     */
    protected function splitAddress($str)
    {
        foreach (explode(',', $str) as $name)
        {
            $str = explode('<', trim($name));

            if (count($str) == 1)
            {
                return ['name' => null, 'email' => trim($str[0], '<>')];
            }
            else
            {
                return ['name' => trim($str[0], ' "'), 'email' => trim($str[1], '>')];
            }
        }

        return null;
    }
}