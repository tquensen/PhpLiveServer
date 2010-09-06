<?php

class PhpLiveServer {
    private $port;
    private $sockets = array();
    private $connections = array();
    private $listeners = array();
    private $master;
    private $address;
    private $allowedOrigins = array('http://127.0.0.1', 'http://localhost');

    public function __construct($port=12345, $address='127.0.0.1', $maxconn = SOMAXCONN)
    {
        $this->port = $port;
        $this->address = $address;

        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($this->master, $this->address, $this->port) or die('Could not bind socket to address');
        socket_listen($this->master, $maxconn) or die('Could not listen to socket');

        $this->addListener('OPTIONS request', array($this, 'handleOptionsRequest'));

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

    public function send($key, $message, $sendHeader = '200 OK', $headerData = array('Content-Type' => 'application/json; charset=UTF-8', 'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT'))
    {
        if (is_array($key) && isset($key['key'])) {
            $key = $key['key'];
        }
        if (isset($this->connections[$key]) && isset($this->sockets[$key])) {
            if (!empty($this->connections[$key]['isWebSocket'])) {
                if (!empty($this->connections[$key]['requestHeaders']['Origin'])) {
                    $origin = $this->connections[$key]['requestHeaders']['Origin'];
                    if (in_array($origin, $this->allowedOrigins)) {
                        $headerData['WebSocket-Origin'] = $origin;
                        $headerData['WebSocket-Location'] = 'ws://'.$this->connections[$key]['requestHeaders']['Host'].$this->connections[$key]['requestAction'];
                        $headerData['Sec-WebSocket-Origin'] = $origin;
                        $headerData['Sec-WebSocket-Location'] = 'ws://'.$this->connections[$key]['requestHeaders']['Host'].$this->connections[$key]['requestAction'];
                    }
                    $header = $this->getHeader($headerData, $sendHeader);
                    $data = $header."\r\n".$message;
                    $data = $data;
                } else {
                    $data = chr(0).$message.chr(255);
                }
                
                echo "SENDING ($sendHeader) $data TO $key\n";
                //$data = $data.chr(0);
                socket_write($this->sockets[$key],$data,strlen($data));
            } else {
                if ($sendHeader) {
                    if (!empty($this->connections[$key]['requestHeaders']['Origin'])) {
                        $origin = $this->connections[$key]['requestHeaders']['Origin'];
                        if (in_array($origin, $this->allowedOrigins)) {
                            $headerData['Access-Control-Allow-Origin'] = $origin;
                            $headerData['Access-Control-Allow-Credentials'] = 'true';
                            $headerData['Access-Control-Allow-Headers'] = 'Content-Type, Depth, User-Agent, X-File-Size, X-Requested-With, If-Modified-Since, X-File-Name, Cache-Control';
                            $headerData['Access-Control-Allow-Methods'] = 'PROPFIND, PROPPATCH, COPY, MOVE, DELETE, MKCOL, LOCK, UNLOCK, PUT, GETLIB, VERSION-CONTROL, CHECKIN, CHECKOUT, UNCHECKOUT, REPORT, UPDATE, CANCELUPLOAD, HEAD, OPTIONS, GET, POST';
                        }
                    }
                    $header = $this->getHeader($headerData, $sendHeader);
                    $data = $header."\r\n".$message;
                    echo "SENDING ($sendHeader) $data TO $key\n";
                } else {
                    $data = $message;
                    //echo "SENDING $message TO $key\n";
                }
                //$message = $this->wrap($message);
                socket_write($this->sockets[$key],$data,strlen($data));
                $this->disconnect($key);
                
            }
        }
    }

    public function handleOptionsRequest($server, $client)
    {
        $this->send($client, '');
    }

    public function handleWebSocket($server, $client, $data)
    {
        $this->connections[$client['key']]['isWebSocket'] = true;
        $headers = array('Upgrade' => 'WebSocket', 'Connection' => 'Upgrade');
        if (!empty($data['headers']['Sec-WebSocket-Key1'])) {
            //$data['headers']['Sec-WebSocket-Key1'] = '18x 6]8vM;54 *(5:  {   U1]8  z [  8';
            //$data['headers']['Sec-WebSocket-Key2'] = '1_ tx7X d  <  nw  334J702) 7]o}` 0';
            $key1digits = preg_replace('/([^0-9]+)*/', '', $data['headers']['Sec-WebSocket-Key1']);
            $key2digits = preg_replace('/([^0-9]+)*/', '', $data['headers']['Sec-WebSocket-Key2']);
            $key1spaces = strlen(preg_replace('/([^ ]+)*/', '', $data['headers']['Sec-WebSocket-Key1']));
            $key2spaces = strlen(preg_replace('/([^ ]+)*/', '', $data['headers']['Sec-WebSocket-Key2']));
            $key1number = (float)$key1digits / $key1spaces;
            $key2number = (float)$key2digits / $key2spaces;
            $sec = substr($data['raw'], -8);
            $str = pack('N', $key1number).pack('N', $key2number).$sec;
            $content = md5($str, true);

            echo "\n\n";
            var_dump($key1digits);
            var_dump($key2digits);
            var_dump($key1spaces);
            var_dump($key2spaces);
            var_dump($key1number);
            var_dump($key2number);
            var_dump($sec);
            echo "\n\n";
        } else {
            $content = '';
        }

        $this->send($client, $content, '101 Web Socket Protocol Handshake', $headers);
    
        return true;
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
        $header = "HTTP/1.1 ".$httpCode."\r\n";
        foreach($headerData as $key => $value) {
            $header .= $key.': '.$value."\r\n";
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
                    $read  = '';
                    socket_set_nonblock($socket);
                    while (($buffer = @socket_read($socket,96, PHP_BINARY_READ))) {
                        $read .= $buffer;
                    }
                    socket_set_block($socket);
                    if (!$read) {
                        $this->disconnect($key);
                    } else {
                        $this->process($key, $read);
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
        //echo "NEW CONNECTION: ".$randomKey."\n";
    }

    private function process($key, $data)
    {
        if (!empty($this->connections[$key]['isWebSocket'])) {
            $data = $this->parseRequest(substr($data,1,strlen($data)-2));
        } else {
            $data = $this->parseRequest($data);
        }
        echo '============================='."\n".$key."\n".'============================='."\n".$data['raw']."\n=============================\n\n";
        if ($data['action']) {
            $this->connections[$key]['requestAction'] = $data['action'];
            $this->connections[$key]['requestHeaders'] = $data['headers'];
            //echo "PROCESSING ".$data['action']." FOR ".$key."\n";
            if (!$this->call(strtoupper($data['method']).' request', $key, $data) && !$this->call(strtoupper($data['method']).' '.$data['action'], $key, $data)) {
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
            $rawHeader = explode(': ', $rawHeader, 2);
            if (trim($rawHeader[0])) {
                $return['headers'][trim($rawHeader[0])] = (!empty($rawHeader[1]) && trim($rawHeader[1])) ? str_replace(array("\r","\n"), '', $rawHeader[1]) : null;
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
