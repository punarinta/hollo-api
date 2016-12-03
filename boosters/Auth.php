<?php

class Auth
{
    const GUEST         = 0;                // not logged in
    const USER          = 0b00000001;       // usual user
    const ADMIN         = 0b10000000;       // admin

    /**
     * Checks if user is logged in
     * Is much faster than user()
     *
     * @return bool
     */
    static function check()
    {
        return isset ($_SESSION['-AUTH']['user']);
    }

    /**
     * Quick access to the current User entity
     *
     * @return mixed
     */
    static function user()
    {
        return \Sys::svc('Auth')->user();
    }

    /**
     * Tells if I have the specified role
     *
     * @param $role
     * @return bool
     */
    static function amI($role)
    {
        if ($role === self::GUEST)
        {
            // true by definition
            return true;
        }

        if (!self::check())
        {
            // no, you don't
            return false;
        }

        return @$_SESSION['-AUTH']['user']->roles & $role;
    }

    /**
     * Returns an array of textual role representations
     *
     * @param $roles
     * @return array
     */
    static function textRoles($roles)
    {
        $texts = [];

        if ($roles & self::ADMIN)       $texts[] = 'admin';
        if ($roles & self::USER)        $texts[] = 'user';
        if ($roles & self::GUEST)       $texts[] = 'guest';

        return $texts;
    }
}
