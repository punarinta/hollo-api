<?php

chdir(__DIR__);
include '../App/Model/Bcrypt.php';

if ($argc < 2) die ("No password provided\n");

$crypt = new \App\Model\Bcrypt;
$hash = $crypt->create($argv[1]);

echo "Hash: '$hash'\n\n";
