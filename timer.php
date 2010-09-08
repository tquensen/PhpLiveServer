#!/usr/bin/php
<?php
error_reporting(E_ALL);

function logger($message)
{
    global $key, $action;
    file_put_contents(dirname(__FILE__).'/timer.log', date('Y.m.d H:i:s') . ': Timer '.$key.' for '.$action.' > '.$message."\n", FILE_APPEND);
}

$script = array_shift($argv);
$address = array_shift($argv);
$port = array_shift($argv);
$key = array_shift($argv);
$interval = array_shift($argv);
$action = array_shift($argv);

$interval = (int) $interval / 1000;
$nextCall = microtime(true)+$interval;

if (!$address || !$port) {
    echo 'address or port missing!';
    exit;
}

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($socket, $address, $port);

$i = 0;
logger('started...');
$read = '';
do {
    $wait = $nextCall - microtime(true);
    //echo 'WAITING '.(int)($wait * 1000000).' microseconds';
    if ($wait > 0) usleep((int)($wait * 1000000));

    $sockets = array($socket);

    $message = 'TIMER '.$action."\r\n\r\n".'key='.$key;
    $len=strlen($message);
    $offset = 0;
    while ($offset < $len) {
        $sent = socket_write($socket, substr($message, $offset), $len-$offset);
        if ($sent === false) {
            //echo 'ERROR WHILE SENDING'."\n";
            // Error occurred, break the while loop
            break;
        }
        $offset += $sent;
    }
    if ($offset < $len) {
        $read = 'sending error: (' . socket_last_error() . ') '. socket_strerror(socket_last_error());
    } else {
        $read  = '';
        if (!socket_select($sockets, $w=null, $e=null, null)) {
            $read = 'reading error: (' . socket_last_error() . ') '. socket_strerror(socket_last_error());
        } else {
            socket_set_nonblock($socket);
            while (($buffer = @socket_read($sockets[0],96, PHP_BINARY_READ))) {
                $read .= $buffer;
            }
            socket_set_block($socket);
        }
    }
    $nextCall += $interval;
    //var_dump($nextCall);
} while($read == 'ok' && ++$i);
logger('shutdown after '.$i.' runs / message: ' .$read);