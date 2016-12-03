<?php

namespace App\Model\CliTool;

use MongoDB\Driver\BulkWrite;

/**
 * Class Once
 * @package App\Model\CliTool
 */
class Once
{
    /**
     * Populate 'muted'
     *
     * @param string $fileName
     */
    public function importMuted($fileName = 'populate/muted.json')
    {
        $bulk = new BulkWrite();
        $elements = json_decode(file_get_contents($fileName));

        foreach ($elements as $element)
        {
            if (!$element->user) unset ($element->user);
            $bulk->insert($element);
        }

        $GLOBALS['-DB-L']->executeBulkWrite('hollo.muted', $bulk);
    }

    /**
     * Populate 'mail_service'
     *
     * @param string $fileName
     */
    public function importServices($fileName = 'populate/mail_service.json')
    {
        $bulk = new BulkWrite();
        $elements = json_decode(file_get_contents($fileName));

        foreach ($elements as $element)
        {
            $bulk->insert(array
            (
                'name'      => $element->name,
                'domains'   => explode('|', trim($element->domains, '|')),
                'cfgIn'     => json_decode($element->cfg_in),
                'cfgOut'    => json_decode($element->cfg_out),
            ));
        }

        $GLOBALS['-DB-L']->executeBulkWrite('hollo.mail_service', $bulk);
    }

    /**
     * Populate 'user'
     *
     * @param string $fileName
     */
    public function importUsers($fileName = 'populate/user.json')
    {
        // TODO: before running set 'svc' and camelCase flags

        $bulk = new BulkWrite();
        $elements = json_decode(file_get_contents($fileName));

        foreach ($elements as $element)
        {
            $array = array
            (
                'email'      => $element->email,
                'roles'      => $element->roles,
                'settings'   => json_decode($element->settings),
                'lastMuid'   => $element->last_muid,
            );

            if ($element->name) $array['name'] = $element->name;

            $bulk->insert($array);
        }

        $GLOBALS['-DB-L']->executeBulkWrite('hollo.user', $bulk);
    }
}
