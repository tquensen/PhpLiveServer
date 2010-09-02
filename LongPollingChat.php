<?php
class LongPollingChat {
    private $users = array();
    private $connections = array();
    private $messages = array();

    public function join($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO JOIN CHAT\n";
        if (empty($data['query']['username'])) {
            $server->send($client, json_encode(array('status' => false, 'message' => 'No Username given!')));
            return;
        }
        $userId = md5($data['query']['username'].rand(10000,99999));
        $this->users[$userId] = array('username' => $data['query']['username'], 'joined' => time(), 'lastActivity' => time(), 'waiting' => array());
        $this->connections[$client['key']] = $userId;
        echo 'USER '.$userId.' JOINED CHAT!'."\n";

        $timestamp = microtime(true);
        list($newMessages, $newTimestamp) = $this->getMessages($timestamp);

        $this->addMessage($data['query']['username'].' joined!', 'join', $userId);
        $this->sendMessages();
        $server->send($client, json_encode(array('status' => true, 'message' => 'You have joined the Chat', 'id' => $userId, 'timestamp' => $timestamp)));
    }

    public function get($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO GET CHAT DATA\n";
        if (empty($data['query']['id']) || !isset($this->users[$data['query']['id']])) {
            echo 'CLIENT '.$client['key']." IS NO VALID CHAT USER!\n";
            $server->send($client, json_encode(array('status' => false, 'message' => 'No valid id given!')));
            return;
        }
        if (isset($this->connections[$client['key']]) && $this->connections[$client['key']] != $data['query']['id']) {
            echo 'CLIENT '.$client['key']." IS ANOTHER CHAT USER THAN ".$data['query']['id']."!\n";
            $server->send($client, json_encode(array('status' => false, 'message' => 'You are already connected as another user o_O')));
            return;
        }
        if (empty($data['query']['timestamp'])) {
            $data['query']['timestamp'] = microtime(true);
        }
        $this->connections[$client['key']] = $data['query']['id'];
        $this->users[$data['query']['id']]['lastActivity'] = time();
        
        
        $this->users[$data['query']['id']]['waiting'][$client['key']] = $data['query']['timestamp'];
        
        $this->sendMessages();
        return true;
    }

    public function get($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO GET CHAT DATA\n";
        if (empty($data['query']['id']) || !isset($this->users[$data['query']['id']])) {
            echo 'CLIENT '.$client['key']." IS NO VALID CHAT USER!\n";
            $server->send($client, json_encode(array('status' => false, 'message' => 'No valid id given!')));
            return;
        }
        if (isset($this->connections[$client['key']]) && $this->connections[$client['key']] != $data['query']['id']) {
            echo 'CLIENT '.$client['key']." IS ANOTHER CHAT USER THAN ".$data['query']['id']."!\n";
            $server->send($client, json_encode(array('status' => false, 'message' => 'You are already connected as another user o_O')));
            return;
        }
        if (empty($data['query']['timestamp'])) {
            $data['query']['timestamp'] = microtime(true);
        }
        $this->connections[$client['key']] = $data['query']['id'];
        $this->users[$data['query']['id']]['lastActivity'] = time();


        $this->users[$data['query']['id']]['waiting'][$client['key']] = $data['query']['timestamp'];

        $this->sendMessages();
        return true;
    }

    public function set($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO SEND CHAT DATA\n";
        if (empty($data['query']['id']) || !isset($this->users[$data['query']['id']])) {
            echo 'CLIENT '.$client['key']." IS NO VALID CHAT USER!\n";
            $server->send($client, json_encode(array('status' => false, 'message' => 'No valid id given!')));
            return;
        }
        if (isset($this->connections[$client['key']]) && $this->connections[$client['key']] != $data['query']['id']) {
            echo 'CLIENT '.$client['key']." IS ANOTHER CHAT USER THAN ".$data['query']['id']."!\n";
            $server->send($client, json_encode(array('status' => false, 'message' => 'You are already connected as another user o_O')));
            return;
        }

        $this->connections[$client['key']] = $data['query']['id'];
        $this->users[$data['query']['id']]['lastActivity'] = time();

        if (empty($data['query']['message'])) {
            $server->send($client, json_encode(array('status' => false, 'message' => 'No message given!')));
        }
        //$this->users[$data['query']['id']]['waiting'][$client['key']] = $data['query']['timestamp'];

        $this->addMessage($this->users[$data['query']['id']]['username'].': '.$data['query']['message'], 'message', $data['query']['id']);

        $this->sendMessages();
        $server->send($client, json_encode(array('status' => true, 'message' => 'message posted!')));
    }

    public function disconnect($server, $client)
    {
        if (isset($this->connections[$client['key']])) {
            $userId = $this->connections[$client['key']];
            unset($this->connections[$client['key']]);
            unset($this->users[$userId]['waiting'][$client['key']]);
            $this->users[$userId]['lastActivity'] = time();
            echo 'REMOVING CONNECTION '.$client['key'].' FROM CHAT USER '.$userId."\n";
        }
    }

    private function addMessage($message, $type, $from, $to = false)
    {
        array_push($this->messages, array('time' => microtime(true), 'message' => $message, 'type' => $type, 'from' => $from, 'to' => $to));
        while (count($this->messages) > $this->maxMessages) {
            array_shift($this->messages);
        }
        $this->sendMessages();
    }

    private function getMessages($fromTime, $for = false)
    {
        $messages = array();
        foreach ($this->messages as $message) {
            if ($message['time'] > $fromTime && ($message['for'] == $for || $message['for'] == false)) {
                $messages[] = array('time' => $message['time'], 'message' => $message['message']);
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

    private function sendMessages()
    {
        foreach ($this->user as $userId => $userData) {
            foreach ($userData['waiting'] as $connection => $timestamp) {
                list($newMessages, $newTimestamp) = $this->getMessages($data['query']['timestamp'], $data['query']['id']);
                if (count($newMessages)) {
                    $server->send($userId, json_encode(array('status' => true, 'message' => count($newMessages).' new messages!', 'timestamp' => $newTimestamp, 'messages' => $newMessages)));
                    return;
                }
            }
        }
    }
}
