<?php

require_once __DIR__ . '/vendor/autoload.php';


use Predis\Client;


try {
    $host = '127.0.0.1';
    $password = null;
    $port = 6379;
    $database = 6;
    $redis = new Client([
            'host'   => $host,
            'port'   => $port,
            'database' => $database
    ]);
}
catch (Exception $e) {
    die ($e->getMessage());
}

?>