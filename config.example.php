<?php
$serverConfig = array(
    'port' => 8080,
    'host' => '127.0.0.1',
    'allowedOrigins' => array('http://127.0.0.1:8080', 'http://locahlost:8080'),
    'mongo' => false,
    'channels' => array(
        'main' => 'Willkommen im Chat!'
    ),
    'maxMessages' => 100
);