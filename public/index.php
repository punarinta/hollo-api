<?php

// 0. Debugging info
$t1 = microtime(1);
$m1 = memory_get_usage();

/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

// 1. Preliminary settings

chdir('..');
date_default_timezone_set('Europe/Stockholm');


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

// Built 27.10.16 @ 11:08:07 +0200

$GLOBALS['-R'] = [
'auth' => ['/api/auth', \Auth::GUEST, '\App\Controller\Auth', 'index'],
'contact' => ['/api/contact', \Auth::USER, '\App\Controller\Contact', 'index'],
'chat' => ['/api/chat', \Auth::USER, '\App\Controller\Chat', 'index'],
'email' => ['/api/email', \Auth::USER, '\App\Controller\Email', 'index'],
'message' => ['/api/message', \Auth::USER, '\App\Controller\Message', 'index'],
'file' => ['/api/file', \Auth::USER, '\App\Controller\File', 'index'],
'settings' => ['/api/settings', \Auth::USER, '\App\Controller\Settings', 'index'],
'gmail-push' => ['/api/gmail-push', \Auth::GUEST, '\App\Controller\GmailPush', 'index'],
];

// 5. Run application

$data    = null;
$errMsg  = '';
$isError = false;
// $GLOBALS['-DBG-SQL'] = [];

try
{
    $data = \Sys::run(require_once 'App/config.php');
}
catch (\Exception $e)
{
    $isError   = true;
    $errMsg    = $e->getMessage();
    $errorCode = $e->getCode() ?: 500;

/*    if ($errorCode == 500 && \Sys::cfg('release') != false)
    {
        // report to Santa about someone's bad behaviour
        \Sys::svc('Resque')->addJob('ReportError',
        [
            'stack'     => $e->getTrace(),
            'msg'       => $errMsg,
            'server'    => $_SERVER,
            'input'     => $GLOBALS['-P-JSON'],
            'user'      => \Auth::user(),
        ]);
    }*/

    http_response_code($errorCode);
}

echo json_encode(array
(
    'isError'   => $isError,
    'errMsg'    => $errMsg,
    'data'      => $data,
    'time'      => number_format((microtime(1) - $t1) * 1000, 2) . ' ms',
    'memory'    => number_format((memory_get_usage() - $m1) / 1024, 2) . ' kB',
//    'debug'     => $GLOBALS['-DBG-SQL'],
));
