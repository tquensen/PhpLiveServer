#!/usr/bin/php
<?php
// start this script via "./chatserver.php > server.log &"
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();
date_default_timezone_set('Europe/Berlin');

include_once dirname(__FILE__).'/config.php';

include_once dirname(__FILE__).'/PhpLiveServer.php';
include_once dirname(__FILE__).'/PhpLiveChat.php';
include_once dirname(__FILE__).'/PhpLiveChatMongo.php';


$server = new PhpLiveServer($serverConfig['port'], $serverConfig['host']);
$server->setAllowedOrigins($serverConfig['allowedOrigins']);

if (!empty($serverConfig['mongo'])) {
    $mongo = new Mongo();
    $chat = new PhpLiveChatMongo($serverConfig['maxMessages']);
    $chat->setDatabase($mongo->chat);

} else {
    $chat = new PhpLiveChat($serverConfig['maxMessages']);
}

foreach ($serverConfig['channels'] as $channelName => $channelDescription) {
    $chat->addChannel($channelName, $channelDescription);
}

//$chat->addChannel('talk', 'Expertenrunde - heute mit Steve Jobs!');
//$chat->addChannel('offtopic', 'Offtopic - hier kann über alles gequatscht werden.');

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


$server->listen() or die('listen failed!');
