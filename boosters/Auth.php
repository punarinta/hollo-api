<?php

class Auth
{
    const GUEST         = 0;                        // not logged in
    const READER        = 0b0000000000000001;       // good old reader
    const TEACHER       = 0b0000000000000010;       // can work with tasks and invite readers
    const ACCOUNTANT    = 0b0000000000000100;       // financial guy
    const EDITOR        = 0b0000000000001000;       // can build courses
    const OWNER         = 0b0001000000000000;       // owns Teh Bznss
    const TESTER        = 0b0010000000000000;       // has access to test features
    const ASSISTANT     = 0b0100000000000000;       // usual admin
    const DARK_ADMIN    = 0b1000000000000000;       // the great lord

    const NOT_READER    = 0b0001000000001110;

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
     * Quick access to the current Profile entity
     *
     * @return bool
     */
    static function profile()
    {
        return \Sys::svc('Auth')->profile();
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

        if ($roles & self::DARK_ADMIN)  $texts[] = 'dark-admin';
        if ($roles & self::ASSISTANT)   $texts[] = 'assistant';
        if ($roles & self::TESTER)      $texts[] = 'tester';
        if ($roles & self::OWNER)       $texts[] = 'owner';
        if ($roles & self::EDITOR)      $texts[] = 'editor';
        if ($roles & self::ACCOUNTANT)  $texts[] = 'accountant';
        if ($roles & self::TEACHER)     $texts[] = 'teacher';
        if ($roles & self::READER)      $texts[] = 'reader';

        return $texts;
    }

    //
    // TODO: remove this function after a while
    //
    static function c2Role()
    {
        if (!isset ($_SESSION['-AUTH']['user'])) return 'guest';

        $roles = $_SESSION['-AUTH']['user']->roles;

        if ($roles & self::ASSISTANT) return 'assistant';
        if ($roles & self::EDITOR) return 'builder';
        if ($roles & self::READER) return 'reader';

        return 'user';
    }
}
