<?php

namespace App\Controller;

use \App\Service\User as UserSvc;

class Generic
{
    /**
     * API entry point
     *
     * @return mixed
     * @throws \Exception
     */
    static public function index()
    {
        // check if method is provided
        if (!$method = \Input::json('method'))
        {
            // maybe it was passed via HTTP GET
            if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !$method = \Input::get('method'))
            {
                throw new \Exception('No payload found or no method specified.', 405);
            }
        }

        // check if this method exists
        if (!method_exists(get_called_class(), $method))
        {
            throw new \Exception('Method \'' . $method . '\' does not exist.', 404);
        }

        // set up proper localization
    /*    if (\Auth::check())
        {
            \Lang::setLocale(\Sys::aPath(json_decode(\Auth::user()->settings, true)?:[]), 'locale');
        }*/

        // setup pagination
        \DB::$pageStart  = \Input::json('pageStart');
        \DB::$pageLength = \Input::json('pageLength');

    //    $_SESSION['-AUTH']['user'] = UserSvc::findOne(['email' => 'hollo.email@gmail.com']);

        return forward_static_call([get_called_class(), $method]);
    }
}