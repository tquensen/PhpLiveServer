#!/usr/bin/php
<?php
// start this script via "./chatserver.php > server.log &"
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();
date_default_timezone_set('Europe/Berlin');


include_once dirname(__FILE__).'/PhpLiveServer.php';
include_once dirname(__FILE__).'/PhpLiveChat.php';


$server = new PhpLiveServer(12345);
$chat = new PhpLiveChat();

$server->addListener('POST /chat/join', array($chat, 'join'));
$server->addListener('POST /chat/get', array($chat, 'get'));
$server->addListener('POST /chat/set', array($chat, 'set'));
$server->addListener('TIMER /chat/timer', array($chat, 'checkActivity'));
$server->addListener('TIMER /chat/timer2', array($chat, 'checkActivity'));
$server->addListener('disconnect', array($chat, 'disconnect'));
$server->addListener('unhandledRequest', array($server, 'handleUnhandledRequest'));
$server->addListener('GET /chat/socket', array($server, 'handleWebSocket'));

$server->startTimer('/chat/timer', 1000);
$server->startTimer('/chat/timer2', 1000);


$server->listen() or die('OMFG!');