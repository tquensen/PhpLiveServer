<?php

class LongPollingServer {
    private $port;
    private $sockets = array();
    private $connections = array();
    private $listeners = array();
    private $master;
    private $address;
    private $allowedOrigins = array('http://t3n.local', 'http://localhost');

    public function __construct($port=12345, $address='127.0.0.1', $maxconn = SOMAXCONN)
    {
        $this->port = $port;
        $this->address = $address;

        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($this->master, $this->address, $this->port) or die('Could not bind socket to address');
        socket_listen($this->master, $maxconn) or die('Could not listen to socket');
        echo "SOCKET CREATED!\n";
    }

    public function disconnect($key)
    {
        if (isset($this->sockets[$key])) {
            $this->call('disconnect', $key);
            socket_close($this->sockets[$key]);
            unset($this->sockets[$key]);
            unset($this->connections[$key]);
            echo "CLOSE CONNECTION: ".$key."\n";
        }
    }

    public function send($key, $message, $disconnect = true, $sendHeader = '200 OK', $headerData = array(), $headerData = array('Content-Type' => 'application/json; charset=UTF-8', 'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT'))
    {
        if (is_array($key) && isset($key['key'])) {
            $key = $key['key'];
        }
        if (isset($this->connections[$key]) && isset($this->sockets[$key])) {
            if ($sendHeader) {
                if (!empty($this->connections[$key]['requestHeaders']['Origin'])) {
                    $origin = $this->connections[$key]['requestHeaders']['Origin'];
                    if (in_array($origin, $this->allowedOrigins)) {
                        $headerData['Access-Control-Allow-Origin'] = $origin;
                    }
                }
                $header = $this->getHeader($headerData, $sendHeader);
                $data = $header."\n".$message;
                echo "SENDING ($sendHeader) $message TO $key\n";
            } else {
                $data = $message;
                echo "SENDING $message TO $key\n";
            }
            //$message = $this->wrap($message);
            socket_write($this->sockets[$key],$data,strlen($data));
            if ($disconnect) {
                $this->disconnect($key);
            }
        }
    }

    public function addListener($event, $callable)
    {
        $this->listeners[$event][] = $callable;
    }

    public function parseQueryString($data)
    {
        $return = array();
        foreach (explode('&',$data) as $parameter)
        {
            $param = explode('=', $parameter, 2);
            $return[trim($param[0])] = isset($param[1]) ? urldecode($param[1]) : '';
        }
        return $return;
    }

    public function removeListener($event, $callable)
    {
        if (!is_array($this->listeners[$event])) {
            return false;
        }
        foreach ($this->listeners[$event] as $key => $listener) {
            if ($listener == $callable) {
                unset($this->listeners[$key]);
                return true;
            }
        }
        return false;
    }

    private function call($event, $key, $data = null)
    {
        if (empty($this->listeners[$event]) || !isset($this->connections[$key])) {
            return false;
        }
        foreach ($this->listeners[$event] as $listener) {
            if (!isset($this->connections[$key]) || true === call_user_func($listener, $this, $this->connections[$key], $data, $event)) {
                return true;
            }
        }
        return false;
    }

    private function getHeader($headerData, $httpCode = '200 OK')
    {
        $header = "HTTP/1.1 ".$httpCode."\n".
                  "Connection: close\n";
        foreach($headerData as $key => $value) {
            $header .= $key.': '.$value."\n";
        }
        return $header;
    }

    public function listen()
    {
        if (!$this->master) {
            return false;
        }
        echo 'LISTENING...'."\n";
        do {
            $sockets = $this->sockets;
            $sockets[] = $this->master;

            if (!socket_select($sockets, $w=null, $e=null, null)) {
                echo socket_strerror(socket_last_error())."\n";
            }
            foreach ($sockets as $socket) {
                if ($socket == $this->master) {
                    $client=socket_accept($socket) or trigger_error('Could not accept socket '.$socket);
                    if ($client) {
                        $this->connect($client);
                    }
                } else {
                    $key = array_search($socket, $this->sockets);
                    $buffer = null;
                    $bytes = socket_recv($socket,$buffer,4096,null);
                    if (!$bytes) {
                        $this->disconnect($key);
                    } else {
                        $this->process($key, $this->parseRequest($buffer));
                    }
                }
            }
        } while($this->master);
    }

    private function connect($socket)
    {
        $randomKey = md5(microtime(true).rand(10000,99999).$socket);
        $this->sockets[$randomKey] = $socket;
        $this->connections[$randomKey] = array('key' => $randomKey, 'socket' => $socket);
        $this->call('connect', $randomKey);
        echo "NEW CONNECTION: ".$randomKey."\n";
    }

    private function process($key, $data)
    {
        if ($data['action']) {
            $this->connections[$key]['requestHeaders'] = $data['headers'];
            echo "PROCESSING ".$data['action']." FOR ".$key."\n";
            if (!$this->call('request', $key, $data) && !$this->call($data['action'], $key, $data)) {
                $this->call('unhandledRequest', $key, $data);
            }

        } else {
            $this->disconnect($key);
        }
    }

    private function parseRequest($buffer)
    {
        $return = array('raw' => $buffer);
        $rawHeaders = explode("\n", $buffer);

        $http = explode(' ', array_shift($rawHeaders), 3);
        $return['method'] = trim(array_shift($http));
        $return['action'] = trim(array_shift($http));
        $return['protocol'] = trim(array_shift($http));
        $return['content'] = '';
        $return['headers'] = array();
        foreach ($rawHeaders as $key => $rawHeader) {
            if (!trim($rawHeader, "\n\r")) {
                unset($rawHeaders[$key]);
                $return['content'] = implode("\n", $rawHeaders);
                break;
            }
            $rawHeader = explode(':', $rawHeader, 2);
            if (trim($rawHeader[0])) {
                $return['headers'][trim($rawHeader[0])] = (!empty($rawHeader[1]) && trim($rawHeader[1])) ? trim($rawHeader[1]) : null;
            }
            unset($rawHeaders[$key]);
        }
        $return['query'] = $this->parseQueryString($return['content']);
        return $return;
    }

    /*
    private function wrap($msg="")
    {
        return chr(0) . $msg . chr(255);
    }

    private function unwrap($msg="")
    {
        return substr($msg, 1, strlen($msg) - 2);
    }
     */
}
