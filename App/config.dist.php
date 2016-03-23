<?php

return array
(
    'release' => false,
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
    ),
    'session' => array(
        'remember_me_seconds' => 2419200,
        'use_cookies' => true,
        'cookie_domain' => '.mailless.dev',
        'allowed_origins' => array
        (
            'http://localhost:9000',
        ),
    ),
    'mailless' => array(
        'api_domain'        => 'app.mailless.dev',
        'app_domain'        => 'app.mailless.dev',
        'this_server'       => 'app.mailless.dev',
        'file_secret_key'   => 'MaillessFTW',
    ),
    'resque' => array(
        'queue'  => 'queue',
        'queue-fast'  => 'queue-fast',
        'db_log' => false,
    ),
    'redis' => array(
        'host'		=> 'c-3p1.s.mailless.io',
        'port'		=> '6379',
        'pass'      => '',
        'cache_pfx' => 'live::',
    ),
    'mandrill' => array(
        'api_key'	=> '',
        'async'     => true,
    ),
    'amazon' => array(
        'api_key'		=> '',
        'secret'		=> '',
        'region'		=> 'US',
        'bucket'		=> 'mailless',
    ),
    'zencoder' => array
    (
        'read_key'      => '',
        'full_key'		=> '',
        'bucket'        => 'mailless',
    ),
    'emails' => array(
        'support'       => 'dev+support@mailless.com',
        'noreply'       => 'dev+noreply@mailless.com',
        'customer'      => 'dev+customer@mailless.com',
    ),
    'social_auth' => array
    (
        'facebook' => array
        (
            'appId'     => '',
            'secret'    => '',
        ),
        'twitter' => array
        (
            'key'     => '',
            'secret'    => '',
        ),
        'google' => array
        (
            'clientId'  => '.apps.googleusercontent.com',
            'secret'    => '',
        ),
    ),
    'sys' => array
    (
        'password_cost' => 14,
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