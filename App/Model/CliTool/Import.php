<?php

namespace App\Model\CliTool;

use App\Service\User as UserSvc;

/**
 * Class Import
 * @package App\Model\CliTool
 */
class Import
{
    /**
     * Import users from list
     *
     * @param $filename
     * @return string
     */
    public function emails($filename)
    {
        if (!$raw = file_get_contents($filename))
        {
            return "Cannot open filename\n";
        }

        foreach (explode("\n", $raw) as $line)
        {
            // give DB some breath
            usleep(20000);

            $line = explode("\t", $line);
            $email = trim(strtolower($line[0]));

            // check existence first
            if (UserSvc::findOne(['email' => $email]))
            {
                echo "Email $email exists.\n";
                continue;
            }

            $name = trim($line[1]);

            if (!strlen($name))
            {
                $name = explode('@', $email);
                $name = $name[0];
            }

            $name = str_replace(['.', ','], [' ', ' '], $name);
            $name = preg_replace('/\s+/', ' ', $name);
            $name = ucwords(strtolower($name));

            UserSvc::create(array
            (
                'email' => $email,
                'name'  => $name,
            ));
        }

        return "OK\n";
    }
}
