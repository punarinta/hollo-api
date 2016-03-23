<?php

class Route
{
    /**
     * Binds a route to a controller.
     * Normally preprocessor will replace all route() calls with one map() call during sorting.
     *
     * @param $name
     * @param $path
     * @param $access
     * @param $controller
     * @param string $method
     */
    static function add($name, $path, $access, $controller, $method = 'index')
    {
        $GLOBALS['-R'][$name] = [$path, $access, $GLOBALS['-C-NS'] . $controller, $method];
    }

    /**
     * Map routes from an array
     *
     * @param $map
     */
    static function map($map)
    {
        $GLOBALS['-R'] = $map;
    }

    /**
     * Generates a URL for an existing view.
     *
     * @param $name
     * @param array $params
     * @return mixed
     */
    static function url($name, $params = [])
    {
        $url = $GLOBALS['-R'][$name][0];

        foreach ($params as $k => $v)
        {
            $url = strtr($url, '{' . $k . '}', $v);
        }

        return $url;
    }
}