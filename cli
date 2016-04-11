<?php

// 0. Debugging info
$t1 = microtime(1);
$m1 = memory_get_usage();

// 1. Preliminary settings

date_default_timezone_set('Europe/Stockholm');
// include_once 'vendor/colinmollenhour/credis/Client.php';


// 2. Load namespaces

$coursioLoaderClassMap = array
(
//    'TCPDF'         => 'vendor/ensepar/tcpdf/tcpdf.php',
//    'HTML2PDF'      => 'vendor/ensepar/html2pdf/HTML2PDF.php',
);

$coursioLoaderPrefixesPsr0 = array
(
    'App\\'         => '.',

    // 3rd parties
//    'Predis'        => 'vendor/predis/predis/lib',
//    'Resque'        => 'vendor/chrisboulton/php-resque/lib',
//    'PHPExcel'      => 'vendor/os/php-excel/PHPExcel',
);

$coursioLoaderPrefixesPsr4 =
    [
        'Stripe\\'      => 'vendor/stripe/stripe-php/lib/',
    ];

spl_autoload_register(function($class) use ($coursioLoaderClassMap, $coursioLoaderPrefixesPsr0, $coursioLoaderPrefixesPsr4)
{
    // class map lookup
    if (isset ($coursioLoaderClassMap[$class]))
    {
        if ($coursioLoaderClassMap[$class])
        {
            include_once $coursioLoaderClassMap[$class];
        }
        else
        {
            return false;
        }
    }

    if (strpos($class, '\\') === false && file_exists('boosters/' . $class . '.php'))
    {
        include_once 'boosters/' . $class . '.php';
        return true;
    }

    foreach ($coursioLoaderPrefixesPsr0 as $namespace => $dir)
    {
        if (0 === strpos($class, $namespace))
        {
            if (strpos($class, '_'))
            {
                $class = strtr(strtr($class, $namespace . '_', ''), '_', '/');
            }

            @include_once $dir . '/' . strtr($class, '\\', '/') . '.php';
            return true;
        }
    }

    foreach ($coursioLoaderPrefixesPsr4 as $namespace => $dir)
    {
        if (0 === strpos($class, $namespace))
        {
            @include_once $dir . strtr(str_replace($namespace, '', $class), '\\', '/') . '.php';
            return true;
        }
    }

    // Remember that this class does not exist.
    return $coursioLoaderClassMap[$class] = false;

}, true, true);


// 3. Cleanup

unset ($coursioLoaderPrefixesPsr0);
unset ($coursioLoaderClassMap);


// 4. Run application

if ($argc < 2)
{
    system('clear');

    echo "\033[0;34m\n";
    echo "\033[0;32mAvailable commands:\n";
    include 'App/Model/CliTool/cli-docs.php';
    echo "\033[0m\n";

    return;
}

$tool = new \App\Model\CliTool(require_once 'App/config.php');
echo $tool->dispatch($argv) . "\n";
