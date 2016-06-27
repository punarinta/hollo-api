<?php

class DocGenerator
{
    protected $tplVariable;
    protected $tplProcedure;
    protected $tplCollection;
    protected $tplLayout;

    protected $rootUrl;
    protected $collectionCount = 0;
    protected $procedureCount = 0;
    protected $config = [];

    /**
     * @return string
     */
    public function build()
    {
        $html = '';

        $configFile = ($_SERVER['argc'] == 2) ? $_SERVER['argv'][1] : 'config.json';

        // load the config file
        $config = json_decode(file_get_contents(__DIR__ . '/' . $configFile), true);

        // fetch templates
        $this->tplVariable   = file_get_contents(__DIR__ . '/templates/variable.html');
        $this->tplProcedure  = file_get_contents(__DIR__ . '/templates/procedure.html');
        $this->tplCollection = file_get_contents(__DIR__ . '/templates/collection.html');
        $this->tplLayout     = file_get_contents(__DIR__ . '/templates/layout.html');
        $this->config = $config;

        $output = isset ($config['output']['filename']) ? $config['output']['filename'] : (getcwd() . '/API.html');

        $this->rootUrl = $config['input']['root_url'];

        // Get files list

        foreach ($config['input']['dirs'] as $directory)
        {
            $files = scandir($directory);
            foreach ($files as $file)
            {
                if (strpos($file, '.php') != false)
                {
                    if (in_array($file, $config['exclude'])) continue;
                    $html .= $this->scanFile($directory . '/' . $file, $directory);
                }
            }
        }

        file_put_contents($output, strtr($this->tplLayout, array
        (
            '[LIST_COLLECTIONS]' => $html,
        )));

        echo "\nBuild complete. Found:\n";
        echo "* {$this->collectionCount} controllers\n";
        echo "* {$this->procedureCount} procedures.\n";
        echo "\n";

        return '';
    }

    /**
     * @param $filePath
     * @param $dirName
     * @return string
     */
    protected function scanFile($filePath, $dirName)
    {
        $html = '';
        $baseUrl = '';

        echo 'Scanning ' . str_replace($dirName, '', $filePath) . "...\n";

        $php = file_get_contents($filePath);

        if (preg_match("/^.*@doc-api-path (.*)\$/m", $php, $matches))
        {
            $baseUrl = trim($matches[1]);
        }

        $m2 = strpos($php, '{', 0);
        $procedures = [];

        while ($m1 = strpos($php, '/**', $m2))
        {
            $m2 = strpos($php, "()\n", $m1);

            if (!$m2) break;

            $procedures[] = explode("\n", str_replace('*', '', substr($php, $m1 + 1, $m2 - $m1 - 1)));
        }

        foreach ($procedures as $procedure)
        {
            $html .= $this->scanProcedure($procedure, $baseUrl);
        }

        $this->collectionCount++;

        // fill the collection and return its markup
        return strtr($this->tplCollection, array
        (
            '[BASE_URL]'       => $baseUrl,
            '[LIST_ENDPOINTS]' => $html,
        ));
    }

    /**
     * @param $procedure
     * @param $baseUrl
     * @return string
     */
    protected function scanProcedure($procedure, $baseUrl)
    {
        $html = '';
        $procedureName = '';
        $description = '';

        $endLine = end($procedure);

        foreach ($procedure as $docLine)
        {
            // first line is a description
            if (!strlen($description))
            {
                $description = trim($docLine);
            }

            if (preg_match_all("/^.*@doc-var (.*)\$/m", $docLine, $matches))
            {
                foreach ($matches[1] as $variable)
                {
                    $html .= $this->scanVariable($variable);
                }
            }

            if ($docLine == $endLine)
            {
                $endLine = explode(' ', $endLine);
                $procedureName = end($endLine);
            }
        }

        $this->procedureCount++;

        return strtr($this->tplProcedure, array
        (
            '[NAME]'           => $procedureName,
            '[DESCRIPTION]'    => $description,
            '[PATH]'           => $baseUrl,
            '[HIDE_FORM]'      => strlen($html) ? '' : 'hidden',
            '[LIST_VARIABLES]' => $html,
        ));
    }

    /**
     * @param $line
     * @return string
     */
    protected function scanVariable($line)
    {
        $type = preg_match("/^.*\\((.*)\\).*\$/m", $line, $varData) ? $varData[1] : 'not provided';
        $name = explode(' - ', preg_replace("/\\(.*\\)/", '', $line));

        $description = count($name) > 1 ? trim($name[1]) : '';

        // is this variable required
        $isImportant = (strpos($name[0], '!') !== false);
        $name = str_replace('!', '', $name[0]);

        // extract default value
        if (strpos($type, '=') !== false)
        {
            $type = explode('=', $type);
            $defaultValue = trim(str_replace("'", '', $type[1]));
            $type = trim($type[0]);
        }
        else
        {
            $defaultValue = '';
        }

        $type = trim($type);

        return strtr($this->tplVariable, array
        (
            '[NAME]'            => trim($name),
            '[DESCRIPTION]'     => trim($description),
            '[TYPE]'            => $type,
            '[DEFAULT_VALUE]'   => trim($defaultValue),
            '[NO_INPUT]'        => /*$type == 'array' ? 'hidden' :*/ '',
            '[CLASS_REQUIRED]'  => $isImportant ? 'required' : '',
        ));
    }
}

(new DocGenerator())->build();
