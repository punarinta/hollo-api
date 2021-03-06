<?php

namespace App\Model\Inbox;

/**
 * Class Generic
 * @package App\Model
 */
class Generic
{
    protected $user = null;

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
            $k = strtolower($k);

            if ($k == 'from')
            {
                $addr['from'] = $this->splitAddress($v[0])[0];
            }
            if ($k == 'to')
            {
                if (!isset ($addr['to'])) $addr['to'] = [];
                $addr['to'] = array_merge($addr['to'], $this->splitAddress($v[0]));
            }
            if ($k == 'cc')
            {
                if (!isset ($addr['cc'])) $addr['cc'] = [];
                $addr['cc'] = array_merge($addr['cc'], $this->splitAddress($v[0]));
            }
            if ($k == 'bcc')
            {
                if (!isset ($addr['bcc'])) $addr['bcc'] = [];
                $addr['bcc'] = array_merge($addr['bcc'], $this->splitAddress($v[0]));
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
        $names = [];

        foreach (explode(',', $str) as $name)
        {
            $str = explode('<', trim($name));

            if (count($str) == 1)
            {
                $names[] = ['name' => null, 'email' => strtolower(trim($str[0], '<>'))];
            }
            else
            {
                $names[] = ['name' => trim($str[0], ' "'), 'email' => strtolower(trim($str[1], '>'))];
            }
        }

        return $names;
    }
}