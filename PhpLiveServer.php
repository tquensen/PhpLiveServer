<?php

class PhpLiveServer {
    private $port;
    private $sockets = array();
    private $connections = array();
    private $listeners = array();
    private $timers = array();
    private $master;
    private $address;
    private $allowedOrigins = array();

    public function __construct($port=12345, $address='127.0.0.1', $maxconn = SOMAXCONN)
    {
        $this->port = $port;
        $this->address = $address;

        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!@socket_bind($this->master, $this->address, $this->port)) {
            $this->log('Could not bind socket to address: ('.socket_last_error().') '.  socket_strerror(socket_last_error()));
            exit;
        }
        if (!@socket_listen($this->master, $maxconn)) {
            $this->log('Could not listen to socket: ('.socket_last_error().') '.  socket_strerror(socket_last_error()));
            exit;
        }

        $this->log('started...');

        $this->addListener('OPTIONS request', array($this, 'handleOptionsRequest'));
        $this->addListener('TIMER request', array($this, 'handleTimerRequest'));

    }

    public function setAllowedOrigins($origins) {
        $this->allowedOrigins = (array) $origins;
    }

    public function disconnect($key)
    {
        if (isset($this->sockets[$key])) {
            $this->call('disconnect', $key);
            socket_close($this->sockets[$key]);
            unset($this->sockets[$key]);
            unset($this->connections[$key]);
            //echo "CLOSE CONNECTION: ".$key."\n";
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
                
                //echo "SENDING ($sendHeader) $data TO $key\n";
                //$data = $data.chr(0);
                socket_write($this->sockets[$key],$data,strlen($data));
            } elseif (!empty($this->connections[$key]['isTimer'])) {
                $data = $message;

                //echo "SENDING ($data) TO $key\n";
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
                    $headerData['Connection'] = 'close';
                    $headerData['Content-Length'] = strlen($message);
                    $header = $this->getHeader($headerData, $sendHeader);
                    $data = $header."\r\n".$message;
                    //echo "SENDING ($sendHeader) $data TO $key\n";
                } else {
                    $data = $message;
                    //echo "SENDING $message TO $key\n";
                }
                //$message = $this->wrap($message);
                socket_write($this->sockets[$key],$data.chr(0),strlen($data));
                $this->disconnect($key);
                
            }
        }
    }

    public function handleOptionsRequest($server, $client)
    {
        $this->send($client, '');
    }

    public function handleTimerRequest($server, $client, $data)
    {
        $this->connections[$client['key']]['isTimer'] = true;
        if (empty($data['query']['key']) || empty($this->timers[$data['query']['key']]) || $this->timers[$data['query']['key']] !== $data['action']) {

            if (isset($this->timers[$data['query']['key']]) && $this->timers[$data['query']['key']] === false) {
                $this->send($client, 'stopped by stopTimer()');
            } else {
                $this->log('got timer request for '.$data['action'].' with invalid key '.(isset($data['query']['key']) ? $data['query']['key'] : '(no key').' from ' . $client['key']);
                $this->send($client, 'invalid key');
            }

        } elseif (!$this->call('TIMER '.$data['action'], $client['key'], $data)) {
            $this->send($client, 'stopped by listener');
        } else {
            $this->send($client, 'ok');
        }
        return true;
    }

    public function handleUnhandledRequest($server, $client, $data, $event)
    {
        $this->log('unhandled request for '.$data['method'].' '.$data['action'].' by '.$client['key'].' / sending 404');
        
        $server->send($client['key'], '', '404 NOT FOUND');
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

            /*
            echo "\n\n";
            var_dump($key1digits);
            var_dump($key2digits);
            var_dump($key1spaces);
            var_dump($key2spaces);
            var_dump($key1number);
            var_dump($key2number);
            var_dump($sec);
            echo "\n\n";
             */
        } else {
            $content = '';
        }

        $this->send($client, $content, '101 Web Socket Protocol Handshake', $headers);
    
        return true;
    }

    public function addListener($event, $callable)
    {
        if (is_array($callable)) {
            if (isset($callable[0]) && isset($callable[1]) && is_object($callable[0])) {
                $name = get_class($callable[0]) . '->' . $callable[1];
            }  else {
                $name = implode('::', $callable);
            }
        } else {
            $name = (string) $callable;
        }
        $this->log('adding listener '.$name.' for action '.$event);
        
        $this->listeners[$event][] = $callable;
    }

    public function startTimer($action, $interval)
    {
        $key = md5(time().$action.rand(10000,99999));
        $file = dirname(__FILE__).'/timer.php';
        $args = $this->address . ' ' . $this->port . ' ' . $key . ' ' . $interval . ' ' . $action;
        $this->log('starting timer '.$key.' for action '.$action);
        $this->timers[$key] = $action;
        //the default shell command
        exec($file . ' ' . $args . ' >/dev/null &');

        //dirty windows/cygwin fix
        //exec('f:\cygwin\bin\bash.exe -c " '.str_replace('\\', '/', $file) . ' ' . $args . ' >/dev/null &"');
        
        
        return $key;
    }

    public function stopTimer($key)
    {
        if (!isset($this->timers[$key])) {
            return false;
        }
        $this->log('stopping timer '.$key.' for action '.$this->timers[$key]);
        $this->timers[$key] = false;

        return true;
    }

    public function stopTimerByAction($action)
    {
        foreach ($this->timers as $key => $timerAction) {
            if ($action === true || $action == $timerAction) {
                $this->stopTimer($key);
            }
        }
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

                if (is_array($callable)) {
                    if (isset($callable[0]) && isset($callable[1]) && is_object($callable[0])) {
                        $name = get_class($callable[0]) . '->' . $callable[1];
                    }  else {
                        $name = implode('::', $callable);
                    }
                } else {
                    $name = (string) $callable;
                }

                $this->log('removing listener '.$name.' for action '.$event);

                return true;
            }
        }
        return false;
    }

    public function log($message)
    {
        file_put_contents(dirname(__FILE__).'/server.log', date('Y.m.d H:i') . ': Server on '.$this->address.':'.$this->port.' > '.$message."\n", FILE_APPEND);
    }

    private function call($event, $key, $data = null)
    {
        if (empty($this->listeners[$event]) || !isset($this->connections[$key])) {
            return false;
        }
        $processed = false;
        foreach ($this->listeners[$event] as $listener) {
            if (!isset($this->connections[$key])) {
                return true;
            }
            $response = call_user_func($listener, $this, $this->connections[$key], $data, $event);
            if($response === false) {
                return true;
            }
            if ($response === true) {
                $processed = true;
            }
        }
        return $processed;
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
        $this->log('listening...');

        do {
            $sockets = $this->sockets;
            $sockets[] = $this->master;

            if (!socket_select($sockets, $w=null, $e=null, null)) {
                echo socket_strerror(socket_last_error())."\n";
            }
            foreach ($sockets as $socket) {
                if ($socket == $this->master) {
                    $client=socket_accept($socket) or $this->log('Could not accept socket '.$socket);
                    if ($client) {
                        $this->connect($client);
                    }
                } else {
                    $key = array_search($socket, $this->sockets);
                    //$buffer = null;
                    $read = '';
                    $buffer = null;
                    $bytes = @socket_recv($socket, $read, 4096, 0);
                    //socket_set_nonblock($socket);
                    //echo "\n\n".'---------------------'."\n";
                    //do {

                    //while ($bytes = @socket_recv($socket, $buffer, 4096, 0)) {//$buffer = @socket_read($socket,1024, PHP_BINARY_READ)) {
                     //   $read .= $buffer;
                    //}
                    //   $read .= $buffer;
                    //   echo $buffer;
                    //} while ($buffer !== '' && $buffer !== false);
                    //echo "\n".'---------------------'."\n\n";
                    //echo $buffer;
                    var_dump($bytes);
                    //socket_set_block($socket);
                    if (!$bytes) {
                        //echo socket_strerror(socket_last_error($socket));
                        $this->disconnect($key);
                    } else {
                        //var_dump($read);
                        $this->process($key, $read);
                    }
                }
            }
        } while($this->master);
        $this->log('shutdown');
    }

    private function connect($socket)
    {
        $randomKey = md5(microtime(true).rand(10000,99999).$socket);
        $this->sockets[$randomKey] = $socket;
        $this->connections[$randomKey] = array('key' => $randomKey, 'socket' => $socket, 'data' => '');
        $this->call('connect', $randomKey);
        //echo "NEW CONNECTION: ".$randomKey."\n";
    }

    private function process($key, $data)
    {
        $this->connections[$key]['data'] .= $data;



        if (!empty($this->connections[$key]['isWebSocket'])) {
            var_dump(ord(substr($this->connections[$key]['data'], -1)));
            if (ord(substr($this->connections[$key]['data'], -1)) !== 255) {
                return;
            } else {
                $data = $this->connections[$key]['data'];
                $this->connections[$key]['data'] = '';
            }
            $data = $this->parseRequest(substr($data,1,-1));
        } else {
            $ok = false;
            if (substr($this->connections[$key]['data'], 0, 5) !== 'POST ' && strpos($this->connections[$key]['data'], "\r\n\r\n")) {
                $data = $this->connections[$key]['data'];
                $this->connections[$key]['data'] = '';
                $data = $this->parseRequest($data);
                $ok = true;
            } elseif(substr($this->connections[$key]['data'], 0, 5) === 'POST ') {
                $data = $this->parseRequest($this->connections[$key]['data']);
                if (!empty($data['headers']['Content-Length']) && strlen($data['content']) >= $data['headers']['Content-Length']) {
                    $this->connections[$key]['data'] = '';
                    $ok = true;
                } else {
                    echo 'waiting for more post content..., GOT '.strlen($data['content']).' NEED '.$data['headers']['Content-Length']."\n";
                }
            }
            if (!$ok) {
                return;
            }
        }
        //echo '============================='."\n".$key."\n".'============================='."\n".$data['raw']."\n=============================\n\n";
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
