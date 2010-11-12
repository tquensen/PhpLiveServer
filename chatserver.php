#!/usr/bin/php
<?php
// start this script via "./chatserver.php > server.log &"
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();
date_default_timezone_set('Europe/Berlin');


include_once dirname(__FILE__).'/PhpLiveServer.php';
include_once dirname(__FILE__).'/PhpLiveChat.php';
include_once dirname(__FILE__).'/PhpLiveChatMongo.php';


$server = new PhpLiveServer(80, '192.168.0.116');
$server->setAllowedOrigins(array('http://t3nchat.local', 'http://192.168.0.116'));

$chat = new PhpLiveChatMongo();

$mongo = new Mongo();
$chat->setDatabase($mongo->chat);

$chat->addChannel('lounge', 'Willkommen in der t3n Lounge! Have fun :)');
$chat->addChannel('talk', 'Expertenrunde - heute mit Steve Jobs!');
$chat->addChannel('offtopic', 'Offtopic - hier kann über alles gequatscht werden.');

$server->addListener('POST /chat/join', array($chat, 'join'));
$server->addListener('POST /chat/get', array($chat, 'get'));
$server->addListener('POST /chat/set', array($chat, 'set'));
$server->addListener('POST /chat/userlist', array($chat, 'getUserList'));
$server->addListener('POST /chat/channellist', array($chat, 'getChannelList'));
$server->addListener('POST /chat/joinChannel', array($chat, 'joinChannel'));
$server->addListener('POST /chat/leaveChannel', array($chat, 'leaveChannel'));

$server->addListener('TIMER /chat/timer', array($chat, 'checkActivity'));
//$server->addListener('TIMER /chat/timer2', array($chat, 'checkActivity'));
$server->addListener('disconnect', array($chat, 'disconnect'));
$server->addListener('unhandledRequest', array($server, 'handleUnhandledRequest'));
$server->addListener('GET /chat/socket', array($server, 'handleWebSocket'));

$server->addListener('GET /', array($chat, 'showIndex'));


$server->startTimer('/chat/timer', 1000);
//$server->startTimer('/chat/timer2', 1000);


$server->listen() or die('OMFG!');