<?php
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();


function send404Response($server, $client, $data, $event)
{
    $server->send($client['key'], '', true, '404 NOT FOUND');
}

include_once dirname(__FILE__).'/LongPollingServer.php';
include_once dirname(__FILE__).'/LongPollingChat.php';


$server = new LongPollingServer(12345);
$chat = new LongPollingChat();

$server->addListener('/chat/join', array($chat, 'join'));
$server->addListener('/chat/get', array($chat, 'get'));
$server->addListener('/chat/set', array($chat, 'set'));
$server->addListener('disconnect', array($chat, 'disconnect'));
$server->addListener('unhandledRequest', 'send404Response');

$server->listen() or die('OMFG!');