<?php

// TODO: add config reading
$config = ['dir' => ['app' => __DIR__ . '/../App', 'public' => __DIR__ . '/../public']];

$code = file_get_contents($config['dir']['app'] . '/routing.php');

$code = str_replace("<?php\n", "\n// Built " . date('d.m.y @ H:i:s O') . "\n", $code);


$controllerNamespace = '\'\\';

// Detect if controller namespace was set

if (preg_match_all('#Controller::setNamespace\((.*?)\)#is', $code, $data, PREG_PATTERN_ORDER))
{
    if (count($data) > 1)
    {
        $controllerNamespace .= trim($data[1][0], ' \'') . '\\';
    }
}

// Build routing table

$routes = [];

if (preg_match_all('#Route::add\((.*?)\)#is', $code, $routesData, PREG_PATTERN_ORDER))
{
    if (count($routesData) > 1)
    {
        foreach ($routesData[1] as $r)
        {
            $r = explode(',', $r);
            $routes[] = [trim($r[0]), trim($r[1]), trim($r[2]), trim($r[3]), isset($r[4]) ? trim($r[4]) : '\'index\''];
        }
    }
}


// Sort routes in the table

usort($routes, function ($a, $b)
{
    $a = count(explode('/', $a[1]));
    $b = (explode('/', $b[1]));

    if ($a === $b) return 0;
    return $a < $b ? -1 : 1;
});


// Replace with map

$rCount = 0;

$code = preg_replace_callback('#Route::add\((.*?)\);\n#is', function () use ($routes, &$rCount)
{
    if ($rCount > 0) return '';
    else
    {
        $rCount++;
        $map = "\$GLOBALS['-R'] = [\n";

        foreach ($routes as $r)
        {
            $map .= '' . $r[0] . ' => [';
            $map .= $r[1] . ', '  . $r[2] . ', ' . $r[3] . ', ' . $r[4];
            $map .= "],\n";
        }

        $map .= '];';

        return $map;
    }
}, $code);

// get index.php template
$indexPhp = file_get_contents($config['dir']['app'] . '/index.src.php');

// fill it with routes
$indexPhp = str_replace('/* [ROUTES] */', $code, $indexPhp);

// generate index.php
file_put_contents($config['dir']['public'] . '/index.php', $indexPhp);

echo "File 'index.php' written to 'public' directory.\n\n";
