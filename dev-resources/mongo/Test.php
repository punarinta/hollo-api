<?php

\Mongo::connect();

$rows = \Mongo::query('messages');

foreach ($rows as $row)
{
    print_r($row);
}