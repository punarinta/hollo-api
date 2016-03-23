<?php

class Sys
{
    /**
     * Runs the application.
     *
     * @param $config
     * @return mixed
     * @throws Exception
     */
    static function run($config)
    {
        $GLOBALS['-CFG'] = $config;

        /*foreach (\Sys::cfg('session.allowed_origins') as $origin)
        {
            if (isset ($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] == $origin)
            {
                header('Access-Control-Allow-Origin: ' . $origin);
            }
        }*/

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Token');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
        {
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            return null;
        }

        // usual request starts

        header('Content-Type: application/json;charset=UTF-8');
        DB::connect();

        if (isset ($_SERVER['HTTP_TOKEN']) && $_SERVER['HTTP_TOKEN'] !== 'null')
        {
            session_id($_SERVER['HTTP_TOKEN']);
        }

        @session_start();

        $uri = rtrim($_SERVER['REQUEST_URI'], '\\');
        $uri = explode('?', $uri);
        $uri = $uri[0];

        // go through set up routes
        foreach ($GLOBALS['-R'] as $v)
        {
            // quick match
            if ($v[0] === $uri)
            {
                if (!\Auth::amI($v[1]))
                {
                    throw new \Exception('Access to the endpoint is not allowed for your role.', 401);
                }

                return forward_static_call([$v[2], $v[3]]);
            }

            // parametrized match
            $regex = preg_replace_callback('#(\{[A-Za-z0-9_]+\})#', function($d)
            {
                // extract names of the route variables
                $GLOBALS['-R-VAR'][str_replace(['{','}'], ['',''], $d[0])] = null;
                return '(.*)';
            }, $v[0]);

            if (strpos($regex, '(') === false) continue;

            if (preg_match('#' . $regex . '#', $uri, $d))
            {
                while (($vv = next($d)) !== false)
                {
                    $GLOBALS['-R-VAR'][key($GLOBALS['-R-VAR'])] = $vv;
                    next($GLOBALS['-R-VAR']);
                }

                if (!\Auth::amI($v[1]))
                {
                    throw new \Exception('Access to the endpoint is not allowed for your role.', 401);
                }

                return forward_static_call([$v[2], $v[3]]);
            }
        }

        throw new \Exception('Endpoint not found', 404);
    }

    /**
     * @param $k
     * @return null
     */
    static function cfg($k)
    {
        return self::aPath($GLOBALS['-CFG'], $k);
    }

    /**
     * Cached access to services
     *
     * @param $service
     * @return mixed
     */
    static function svc($service)
    {
        if (!isset ($GLOBALS['-SVC'][$service]))
        {
            $class = '\App\Service\\' . $service;
            $GLOBALS['-SVC'][$service] = new $class;
        }

        return $GLOBALS['-SVC'][$service];
    }

    /**
     * Provides an APath access to the array element.
     *
     * @param $a
     * @param null $k
     * @return null
     */
    static function aPath($a, $k = null)
    {
        // return full object
        if ($k === null) return $a;

        // I forgot what
        if (empty ($a)) return null;

        $k = [0, $k];

        while (1)
        {
            $k = explode('.', $k[1], 2);

            if (isset ($a[$k[0]])) $a = $a[$k[0]];
            else return null;

            if (count($k) === 1) break;
        }

        return $a;
    }
}