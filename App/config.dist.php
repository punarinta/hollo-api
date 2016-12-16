<?php

return array
(
    'release' => 'replaceString',
    'dir' => array
    (
        'app'   => '.',
    ),
    'db' => array
    (
        'replica'   => null,
        'mongo'     => ['127.0.0.1:27017'],
    ),
    'notifier' => array
    (
        'host'      => '127.0.0.1',
        'port'      => 1488,
        'origin'    => 'http://localhost',
        'ssl'       => false,
        'firebase'  => '',
    ),
    'mailless' => array
    (
        'app_domain'        => 'app.hollo.dev',
        'this_server'       => 'api.hollo.dev',
        'file_secret_key'   => 'HolloFTW',
    ),
    'contextio' => array
    (
        'key'       => '',
        'secret'    => '',
    ),
    'resque' => array
    (
        'queue'  => 'hollo',
        'db_log' => false,
    ),
    'redis' => array
    (
        'host'		=> 'c-3p1.s.coursio.com',   // use 'public' Redis server
        'port'		=> '6379',
        'pass'      => '',
        'cache_pfx' => 'live::',
    ),
    'oauth' => array
    (
        'google' => array
        (
            'clientId'  => '.apps.googleusercontent.com',
            'secret'    => '',
        ),
        'yahoo'  => array
        (
            'clientId'  => '',
            'secret'    => '',
        ),
    ),
    'sys' => array
    (
        'password_cost' => 14,
        'sync_depth'    => 8,
        'sync_period'   => 8640000,
        'imap_hash'     => '',
    ),
);