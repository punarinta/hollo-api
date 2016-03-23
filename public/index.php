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
//    'Resque'    => 'vendor/chrisboulton/php-resque/lib',
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

// Built 15.03.16 @ 14:39:35 +0100

$GLOBALS['-R'] = [
'invite' => ['/api/invite', \Auth::TEACHER, '\App\Controller\Invite', 'index'],
'mr-wolf' => ['/api/mr.wolf-{path}', \Auth::ASSISTANT, '\App\Controller\MrWolf\Winston', 'index'],
'user' => ['/api/user', \Auth::TEACHER, '\App\Controller\User', 'index'],
'invitation' => ['/api/invitation', \Auth::OWNER, '\App\Controller\Invitation', 'index'],
'store' => ['/api/store', \Auth::GUEST, '\App\Controller\Store', 'index'],
'task-result' => ['/api/task-result', \Auth::READER, '\App\Controller\TaskResult', 'index'],
'post' => ['/api/post', \Auth::READER, '\App\Controller\Post', 'index'],
'guest' => ['/api/guest', \Auth::GUEST, '\App\Controller\Guest', 'index'],
'circle' => ['/api/circle', \Auth::TEACHER, '\App\Controller\Circle', 'index'],
'search' => ['/api/search', \Auth::READER, '\App\Controller\Search', 'index'],
'stripe' => ['/api/vendor/stripe', \Auth::OWNER, '\App\Controller\Vendor\Stripe', 'index'],
'hipchat' => ['/api/vendor/hipchat', \Auth::GUEST, '\App\Controller\Vendor\Hipchat', 'inbound'],
'mandrill' => ['/api/vendor/mandrill', \Auth::TEACHER, '\App\Controller\Vendor\Mandrill', 'index'],
'zapier-unsubscribe' => ['/api/vendor/zapier/unsubscribe', \Auth::GUEST, '\App\Controller\Vendor\Zapier', 'unsubscribe'],
'zapier-subscribe' => ['/api/vendor/zapier', \Auth::GUEST, '\App\Controller\Vendor\Zapier', 'index'],
'discount' => ['/api/discount', \Auth::READER, '\App\Controller\Discount', 'index'],
'payment' => ['/api/payment', \Auth::READER, '\App\Controller\Payment', 'index'],
'payment-incoming' => ['/api/payment/incoming', \Auth::ACCOUNTANT, '\App\Controller\PaymentIncoming', 'index'],
'trigger' => ['/api/trigger', \Auth::EDITOR, '\App\Controller\Trigger', 'index'],
'tag' => ['/api/tag', \Auth::TEACHER, '\App\Controller\Tag', 'index'],
'statistics' => ['/api/statistics', \Auth::ACCOUNTANT, '\App\Controller\Statistics', 'index'],
'content' => ['/api/content', \Auth::READER, '\App\Controller\Content', 'index'],
'course' => ['/api/course', \Auth::READER, '\App\Controller\Course', 'index'],
'settings' => ['/api/settings', \Auth::READER, '\App\Controller\Settings', 'index'],
'notifications' => ['/api/notifications', \Auth::READER, '\App\Controller\Notifications', 'index'],
'website' => ['/api/website', \Auth::GUEST, '\App\Controller\Website', 'index'],
'access' => ['/api/access', \Auth::READER, '\App\Controller\Access', 'index'],
'dashboard' => ['/api/dashboard', \Auth::READER, '\App\Controller\Dashboard', 'index'],
'course-package' => ['/api/course-package', \Auth::READER, '\App\Controller\CoursePackage', 'index'],
'page' => ['/api/page', \Auth::READER, '\App\Controller\Page', 'index'],
'upload-encoded' => ['/api/upload/encoded', \Auth::READER, '\App\Controller\Upload', 'encoded'],
'file' => ['/api/file', \Auth::READER, '\App\Controller\File', 'index'],
'task' => ['/api/task', \Auth::READER, '\App\Controller\Task', 'index'],
'upload-plain' => ['/api/upload/plain', \Auth::READER, '\App\Controller\Upload', 'plain'],
'section' => ['/api/section', \Auth::READER, '\App\Controller\Section', 'index'],
'page-row' => ['/api/page-row', \Auth::READER, '\App\Controller\PageRow', 'index'],
'page-module' => ['/api/page-module', \Auth::READER, '\App\Controller\PageModule', 'index'],
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

    if ($errorCode == 500 && \Sys::cfg('release') != false)
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
    }

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
