<?php

namespace App\Model;

/**
 * Bcrypt algorithm using crypt()
 *
 * Class Bcrypt
 * @package App\Model
 */
class Bcrypt
{
    protected $cost = 14;

    /**
     * Bcrypt
     *
     * @param $password
     * @return string
     * @throws \Exception
     */
    public function create($password)
    {
        $salt = self::getBytes(16);

        $salt64 = substr(strtr(base64_encode($salt), '+', '.'), 0, 22);

        if (strlen($hash = crypt($password, '$2y$' . $this->cost . '$' . $salt64)) < 13)
        {
            throw new \Exception('Error during the bcrypt generation');
        }

        return $hash;
    }

    /**
     * Verify if a password is correct against an hash value
     *
     * @param $password
     * @param $hash
     * @return bool
     * @throws \Exception
     */
    public static function verify($password, $hash)
    {
        return crypt($password, $hash) === $hash;
    }

    /**
     * Set the cost parameter
     *
     * @param $cost
     * @return $this
     * @throws \Exception
     */
    public function setCost($cost)
    {
        if (!empty ($cost))
        {
            $cost = (int) $cost;
            if ($cost < 4 || $cost > 31)
            {
                throw new \Exception('The cost of bcrypt must be in range 4-31');
            }

            $this->cost = sprintf('%1$02d', $cost);
        }

        return $this;
    }

    /**
     * Generate random bytes using OpenSSL or Mcrypt
     *
     * @param $length
     * @return bool|string
     * @throws \Exception
     */
    public static function getBytes($length)
    {
        if ($length <= 0)
        {
            return false;
        }

        if (function_exists('openssl_random_pseudo_bytes'))
        {
            $bytes = openssl_random_pseudo_bytes($length, $usable);
            if (true === $usable)
            {
                return $bytes;
            }
        }
        if (function_exists('mcrypt_create_iv'))
        {
            $bytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
            if ($bytes !== false && strlen($bytes) === $length)
            {
                return $bytes;
            }
        }

        throw new \Exception('This PHP environment doesn\'t support secure random number generation. Please consider installing the OpenSSL and/or Mcrypt extensions');
    }
}