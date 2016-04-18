<?php

// 0. Debugging info
$t1 = microtime(1);
$m1 = memory_get_usage();

// 1. Preliminary settings

chdir('..');
date_default_timezone_set('Europe/Stockholm');


// 2. Load namespaces

$autoClasses0 =
[
    'App\\'     => '.',

    // 3rd parties
//    'Predis'    => 'vendor/predis/predis/lib',
    'Resque'    => 'vendor/chrisboulton/php-resque/lib',
//    'Google'    => 'vendor/google/apiclient/src',
];

$autoClasses4 =
[
//    'Facebook\\' => 'vendor/facebook/php-sdk-v4/src/Facebook/',
];

spl_autoload_register(function ($class) use ($autoClasses0, $autoClasses4)
{
    if (strpos($class, '\\') === false && file_exists('boosters/' . $class . '.php'))
    {
        include_once 'boosters/' . $class . '.php';
        return true;
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
            @include_once $dir . strtr(str_replace($namespace, '', $class), '\\', '/') . '.php';
            return true;
        }
    }

    // Remember that this class does not exist.
    // return $manualClasses[$class] = false;
    return false;

}, true, true);


// 3. Cleanup

unset ($autoClasses0, $autoClasses4);

// 4. Compiled routes

// Built 18.04.16 @ 12:58:43 +0200

$GLOBALS['-R'] = [
'profile' => ['/api/profile', \Auth::USER, '\App\Controller\Profile', 'index'],
'context-io' => ['/api/context-io', \Auth::GUEST, '\App\Controller\ContextIO', 'index'],
'file' => ['/api/file', \Auth::USER, '\App\Controller\File', 'index'],
'message' => ['/api/message', \Auth::USER, '\App\Controller\Message', 'index'],
'contact' => ['/api/contact', \Auth::USER, '\App\Controller\Contact', 'index'],
'email' => ['/api/email', \Auth::USER, '\App\Controller\Email', 'index'],
'auth' => ['/api/auth', \Auth::GUEST, '\App\Controller\Auth', 'index'],
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
