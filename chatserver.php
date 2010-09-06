<?php
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();


function send404Response($server, $client, $data, $event)
{
    $server->send($client['key'], '', true, '404 NOT FOUND');
}

include_once dirname(__FILE__).'/PhpLiveServer.php';
include_once dirname(__FILE__).'/PhpLiveChat.php';


$server = new PhpLiveServer(12345);
$chat = new PhpLiveChat();

$server->addListener('POST /chat/join', array($chat, 'join'));
$server->addListener('POST /chat/get', array($chat, 'get'));
$server->addListener('POST /chat/set', array($chat, 'set'));
$server->addListener('disconnect', array($chat, 'disconnect'));
$server->addListener('unhandledRequest', 'send404Response');
$server->addListener('GET /chat/socket', array($server, 'handleWebSocket'));

$server->listen() or die('OMFG!');