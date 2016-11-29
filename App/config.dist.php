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
        'host'      => '127.0.0.1',
        'port'      => '3306',
        'user'      => 'root',
        'pass'      => '',
        'database'  => '',
        'mongo'     => '127.0.0.1:27017',
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
        'api_domain'        => 'api.hollo.dev',
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
    'amazon' => array
    (
        'api_key'		=> '',
        'secret'		=> '',
        'region'		=> 'US',
        'bucket'		=> 'hollo',
    ),
    'emails' => array
    (
        'support'       => 'dev+support@hollo.email',
        'noreply'       => 'dev+noreply@hollo.email',
    ),
    'oauth' => array
    (
        'google' => array
        (
            'clientId'  => '.apps.googleusercontent.com',
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
    'mime' => array
    (
        // data
        'dummy'             => '.data',
        'text/plain'        => '.txt',
        'application/json'  => '.json',
        'application/pdf'   => '.pdf',
        'application/zip'   => '.zip',
        'application/x-zip-compressed' => '.zip',

        'application/msword' => '.doc',
        'application/vnd.ms-excel' => '.xls',
        'application/msexcel' => '.xls',
        'application/vnd.ms-powerpoint' => '.ppt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '.pptx',
        'application/vnd.oasis.opendocument.text' => '.odt',
        'application/vnd.oasis.opendocument.spreadsheet' => '.ods',
        'application/vnd.oasis.opendocument.presentation' => '.odp',

        // images
        'image/jpeg'        => '.jpg',
        'image/jpg'         => '.jpg',
        'image/png'         => '.png',
        'image/gif'         => '.gif',
        'image/bmp'         => '.bmp',
        'image/tiff'        => '.tiff',

        // video
        'video/quicktime'   => '.mp4',
        'video/mp4'         => '.mp4',
        'video/mpeg'        => '.mpg',
        'video/webm'        => '.webm',
        'video/x-ms-wmv'    => '.wmv',
        'video/3gpp'        => '.3gp',
        'video/3gpp2'       => '.3g2',
        'video/x-matroska'  => '.mkv',
        'video/avi'         => '.avi',
        'video/x-flv'       => '.flv',

        // audio
        'audio/ogg'         => '.ogg',
        'audio/mpeg'        => '.mp3',
        'audio/mp3'         => '.mp3',
        'audio/vnd.wave'    => '.wav',
        'audio/wav'         => '.wav',
        'audio/x-m4a'       => '.m4a',
    ),
);