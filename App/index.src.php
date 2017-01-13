<?php

// 0. Debugging info
$t1 = microtime(1);
$m1 = memory_get_usage();

/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

// 1. Preliminary settings

chdir('..');
date_default_timezone_set('UTC'/*'Europe/Stockholm'*/);


// 2. Load namespaces

$autoClasses0 =
[
    'App\\'     => '.',

    'Resque'        => 'vendor/chrisboulton/php-resque/lib',
    'RandomLib'     => 'vendor/ircmaxell/random-lib/lib',
    'SecurityLib'   => 'vendor/ircmaxell/security-lib/lib',
    'Google'        => 'vendor/google/apiclient/src',
];

$autoClasses4 =
[
    'League\\OAuth2\\Client\\' => ['vendor/league/oauth2-client/src/', 'vendor/league/oauth2-google/src/'],
    'Psr\\Http\\Message\\'  => 'vendor/psr/http-message/src/',
    'GuzzleHttp\\Psr7\\'    => 'vendor/guzzlehttp/psr7/src/',
    'GuzzleHttp\\Promise\\' => 'vendor/guzzlehttp/promises/src/',
    'GuzzleHttp\\'          => 'vendor/guzzlehttp/guzzle/src/',
    'EmailAuth\\'           => 'vendor/punarinta/email-auth/src/',
];

$loaderClassMap =
[
];

spl_autoload_register(function ($class) use ($autoClasses0, $autoClasses4, $loaderClassMap)
{
    if (strpos($class, '\\') === false && file_exists('boosters/' . $class . '.php'))
    {
        include_once 'boosters/' . $class . '.php';
        return true;
    }

    if (isset ($loaderClassMap[$class]))
    {
        if ($loaderClassMap[$class])
        {
            include_once $loaderClassMap[$class];
        }
        else
        {
            return false;
        }
    }

    foreach ($autoClasses0 as $namespace => $dir)
    {
        if (0 === strpos($class, $namespace))
        {
            if (strpos($class, '_'))
            {
                $class = strtr(strtr($class, $namespace . '_', ''), '_', '/');
            }

            include_once $dir . '/' . strtr($class, '\\', '/') . '.php';
            return true;
        }
    }

    foreach ($autoClasses4 as $namespace => $dir)
    {
        if (0 === strpos($class, $namespace))
        {
            if (is_array($dir))
            {
                foreach ($dir as $dirry)
                {
                    $file = $dirry . strtr(str_replace($namespace, '', $class), '\\', '/') . '.php';
                    if (file_exists($file))
                    {
                        include_once $file;
                        return true;
                    }
                }
            }
            else
            {
                include_once $dir . strtr(str_replace($namespace, '', $class), '\\', '/') . '.php';
                return true;
            }
        }
    }

    // Remember that this class does not exist.
    // return $manualClasses[$class] = false;
    return false;

}, true, true);


// 3. Cleanup

unset ($autoClasses0, $autoClasses4, $loaderClassMap);

// 4. Compiled routes
/* [ROUTES] */

// 5. Run application

$data    = null;
$errMsg  = '';
$isError = false;

try
{
    $data = \Sys::run(require_once 'App/config.php');
}
catch (\Exception $e)
{
    $isError   = true;
    $errMsg    = $e->getMessage();
    $errorCode = $e->getCode() ?: 500;

    http_response_code($errorCode);

    if ($errorCode == 500 && \Sys::cfg('release') != false)
    {
        // report to Santa about someone's bad behaviour
        \App\Service\Resque::addJob('ReportError',
        [
            'stack'     => $e->getTrace(),
            'msg'       => $errMsg,
            'server'    => $_SERVER,
            'input'     => @$GLOBALS['-P-JSON'],
            'user'      => \Auth::user(),
        ]);
    }
}

// object return is reserved for special purposes
if (!is_object($data) || get_class($data) != 'Silent')
{
    echo json_encode(array
    (
        'isError'   => $isError,
        'errMsg'    => $errMsg,
        'data'      => $data,
        'time'      => number_format((microtime(1) - $t1) * 1000, 2) . ' ms',
        'memory'    => number_format((memory_get_usage() - $m1) / 1024, 2) . ' kB',
    ));
}