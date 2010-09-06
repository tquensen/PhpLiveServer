<?php
class PhpLiveChat {
    private $users = array();
    private $connections = array();
    private $messages = array();
    private $maxMessages = 0;

    public function __construct($maxMessages = 100)
    {
        $this->maxMessages = $maxMessages;
    }
    
    public function join($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO JOIN CHAT\n";
        if (empty($data['query']['username'])) {
            $server->send($client, json_encode(array('action' => 'join', 'status' => false, 'message' => 'No Username given!')));
            return true;
        }
        $userId = md5($data['query']['username'].rand(10000,99999));
        $this->users[$userId] = array('username' => $data['query']['username'], 'joined' => time(), 'lastActivity' => time(), 'waiting' => array());
        //$this->connections[$client['key']] = $userId;
        echo 'USER '.$userId.' JOINED CHAT!'."\n";

        $timestamp = microtime(true);

        if (!empty($client['isWebSocket'])) {
            $this->users[$userId]['waiting'][$client['key']] = $timestamp;
            $this->connections[$client['key']] = $userId;
        }
        
        $this->addMessage($data['query']['username'].' joined!', 'join', $userId);
        $this->sendMessages($server);

        

        $server->send($client, json_encode(array('action' => 'join', 'status' => true, 'message' => 'You have joined the Chat', 'id' => $userId, 'timestamp' => $timestamp - 0.0001)));
    
        return true;
    }

    public function get($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO GET CHAT DATA\n";
        if (empty($data['query']['id']) || !isset($this->users[$data['query']['id']])) {
            echo 'CLIENT '.$client['key']." IS NO VALID CHAT USER!\n";
            echo $data['raw']."\n\n";
            $server->send($client, json_encode(array('action' => 'get', 'status' => false, 'message' => 'No valid id given!')));
            return true;
        }
        /*
        if (isset($this->connections[$client['key']]) && $this->connections[$client['key']] != $data['query']['id']) {
            echo 'CLIENT '.$client['key']." IS ANOTHER CHAT USER THAN ".$data['query']['id']."!\n";
            $server->send($client, json_encode(array('status' => false, 'message' => 'You are already connected as another user o_O')));
            return;
        }
         */
        if (empty($data['query']['timestamp'])) {
            $data['query']['timestamp'] = microtime(true);
        }
        
        $this->connections[$client['key']] = $data['query']['id'];
        $this->users[$data['query']['id']]['lastActivity'] = time();
        
        
        $this->users[$data['query']['id']]['waiting'][$client['key']] = $data['query']['timestamp'];
        
        $this->sendMessages($server);
        return true;
    }

    public function set($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO SEND CHAT DATA\n";
        if (empty($data['query']['id']) || !isset($this->users[$data['query']['id']])) {
            echo 'CLIENT '.$client['key']." IS NO VALID CHAT USER!\n";
            $server->send($client, json_encode(array('action' => 'set', 'status' => false, 'message' => 'No valid id given!')));
            return true;
        }
        /*
        if (isset($this->connections[$client['key']]) && $this->connections[$client['key']] != $data['query']['id']) {
            echo 'CLIENT '.$client['key']." IS ANOTHER CHAT USER THAN ".$data['query']['id']."!\n";
            $server->send($client, json_encode(array('status' => false, 'message' => 'You are already connected as another user o_O')));
            return;
        }
         */

        //$this->connections[$client['key']] = $data['query']['id'];
        $this->users[$data['query']['id']]['lastActivity'] = time();

        if (empty($data['query']['message'])) {
            $server->send($client, json_encode(array('action' => 'set', 'status' => false, 'message' => 'No message given!')));
        }
        //$this->users[$data['query']['id']]['waiting'][$client['key']] = $data['query']['timestamp'];
echo '<'.$data['query']['message'].'>';
        $this->addMessage($this->users[$data['query']['id']]['username'].': '.$data['query']['message'], 'message', $data['query']['id']);
        
        $this->sendMessages($server);
        $server->send($client, json_encode(array('action' => 'set', 'status' => true, 'message' => 'message posted!')));
        return true;
    }

    public function disconnect($server, $client)
    {
        if (isset($this->connections[$client['key']])) {
            $userId = $this->connections[$client['key']];
            unset($this->connections[$client['key']]);
            unset($this->users[$userId]['waiting'][$client['key']]);
            $this->users[$userId]['lastActivity'] = time();
            //$this->checkActivity($server);
            echo 'REMOVING CONNECTION '.$client['key'].' FROM CHAT USER '.$userId."\n";
        }
    }

    private function addMessage($message, $type, $from, $to = false)
    {
        array_push($this->messages, array('time' => microtime(true), 'message' => $message, 'type' => $type, 'from' => $from, 'to' => $to));
        while (count($this->messages) > $this->maxMessages) {
            array_shift($this->messages);
        }
        //$this->sendMessages();
    }

    private function getMessages($fromTime, $to = false)
    {
        $messages = array();
        foreach ($this->messages as $message) {
            if ($message['time'] > (float) $fromTime && ($message['to'] == $to || $message['to'] == false)) {
                $messages[] = array('time' => $message['time'], 'type' => $message['type'], 'message' => $message['message']);
                //array_push($messages, $message);
            }
        }
        if ($last = end($messages)) {
            $last = $last['time'];
        } else {
            $last = $fromTime;
        }
        return array($messages, $last);
    }

    private function sendMessages($server)
    {
        $this->checkActivity($server);
        
        foreach ($this->users as $userId => $userData) {
            foreach ($userData['waiting'] as $connection => $timestamp) {
                list($newMessages, $newTimestamp) = $this->getMessages($timestamp, $userId);
                if (count($newMessages)) {
                    $this->users[$userId]['waiting'][$connection] = $newTimestamp;
                    echo 'SENDING MESSAGES TO '.$userId."\n";
                    $server->send($connection, json_encode(array('action' => 'get', 'status' => true, 'message' => count($newMessages).' new messages!', 'timestamp' => (float) $newTimestamp + 0.0001, 'messages' => $newMessages)));
                    //return;
                }
            }
        }
        
    }

    private function checkActivity($server)
    {
        foreach ($this->users as $userId => $user) {
            if (empty($user['waiting']) && $user['lastActivity'] < time() - 10) {
                echo 'DISCONNECTING USER '.$userId."\n";
                $this->addMessage($user['username'].' left!', 'left', $userId);
                unset($this->users[$userId]);
            }
        }
        //$this->sendMessages($server);
    }
}
