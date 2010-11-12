<?php
class PhpLiveChat {
    private $users = array();
    private $connections = array();
    private $messages = array();
    private $maxMessages = 0;
    private $channels = array();

    public function __construct($maxMessages = 100)
    {
        $this->maxMessages = $maxMessages;
    }

    public function addChannel($name, $description)
    {
        $this->channels[$name] = $description;
    }

    public function removeChannel($name)
    {
        if (isset($this->channels[$name])) {
            unset($this->channels[$name]);
            //TODO: remove users
        }
    }

    public function showIndex($server, $client, $data)
    {
        $data = file_get_contents(dirname(__FILE__).'/index.php');
        $server->send($client, $data, '200 OK', array('Content-Type' => 'text/html; charset=UTF-8', 'Content-Length' =>  strlen($data)));
        return true;
    }
    
    public function join($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO JOIN CHAT\n";

        if (empty($data['query']['username'])) {
            //echo $data['raw'];
            $server->send($client, json_encode(array('action' => 'join', 'status' => false, 'message' => 'No Username given!')));
            return true;
        }
        if (empty($data['query']['channel'])) {
            //echo $data['raw'];
            $server->send($client, json_encode(array('action' => 'join', 'status' => false, 'message' => 'No Channel given!')));
            return true;
        }
        if (!isset($this->channels[$data['query']['channel']])) {
            //echo $data['raw'];
            $server->send($client, json_encode(array('action' => 'join', 'status' => false, 'message' => 'Invalid Channel '.htmlspecialchars($data['query']['channel']).'!')));
            return true;
        }
        $userId = md5($data['query']['username'].rand(10000,99999));
        $this->users[$userId] = array('username' => $data['query']['username'], 'joined' => time(), 'lastActivity' => time(), 'waiting' => array(), 'channels' => array());
        //$this->connections[$client['key']] = $userId;
        echo 'USER '.$userId.' JOINED CHAT (Channel '.htmlspecialchars($data['query']['channel']).')!'."\n";

        $timestamp = isset($data['query']['timestamp']) ? (float) $data['query']['timestamp'] : microtime(true);

        if (!empty($client['isWebSocket'])) {
            $this->users[$userId]['waiting'][$client['key']] = $timestamp;
            $this->connections[$client['key']] = $userId;
        }
        
        $this->users[$userId]['channels'][$data['query']['channel']] = true;

        
        $this->addMessage($data['query']['username'].' joined!', 'join', $data['query']['channel'], $userId);
        $this->addMessage($this->channels[$data['query']['channel']], 'welcome', $data['query']['channel'], $userId, $userId);

        $server->send($client, json_encode(array('action' => 'join', 'status' => true, 'channel' => $data['query']['channel'], 'message' => 'You have joined the Chat (Channel '.htmlspecialchars($data['query']['channel']).')', 'id' => $userId, 'timestamp' => $timestamp - 0.0001)));
        $this->sendMessages($server);
    
        return true;
    }

    public function get($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO GET CHAT DATA\n";
        if (empty($data['query']['id']) || !isset($this->users[$data['query']['id']])) {
            echo 'CLIENT '.$client['key']." IS NO VALID CHAT USER!\n";
            $server->send($client, json_encode(array('action' => 'get', 'status' => false, 'message' => 'No valid id given ('.(empty($data['query']['id']) ? 'no id' : $data['query']['id']).')!')));
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
            $server->send($client, json_encode(array('action' => 'set', 'status' => false, 'message' => 'No valid id given ('.(empty($data['query']['id']) ? 'no id' : $data['query']['id']).')!')));
            return true;
        }
        if (empty($data['query']['channel']) || !isset($this->users[$data['query']['id']]['channels'][$data['query']['channel']])) {
            echo 'CLIENT '.$client['key']." IS NOT IN CHANNEL ".$data['query']['channel']."!\n";
            $server->send($client, json_encode(array('action' => 'set', 'status' => false, 'message' => 'No valid channel given ('.$data['query']['channel'].')!')));
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
        $this->addMessage($this->users[$data['query']['id']]['username'].': '.$data['query']['message'], 'message', $data['query']['channel'], $data['query']['id']);
        
        $server->send($client, json_encode(array('action' => 'set', 'status' => true, 'message' => 'message posted!')));
        $this->sendMessages($server);
        return true;
    }

    public function joinChannel($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO JOIN CHANNEL\n";
        if (empty($data['query']['id']) || !isset($this->users[$data['query']['id']])) {
            echo 'CLIENT '.$client['key']." IS NO VALID CHAT USER!\n";
            $server->send($client, json_encode(array('action' => 'joinChannel', 'status' => false, 'message' => 'No valid id given ('.(empty($data['query']['id']) ? 'no id' : $data['query']['id']).')!')));
            return true;
        }
        if (empty($data['query']['channel']) || !isset($this->channels[$data['query']['channel']]) || isset($this->users[$data['query']['id']]['channels'][$data['query']['channel']])) {
            echo 'CLIENT '.$client['key']." CANT JOIN CHANNEL ".$data['query']['channel']."!\n";
            $server->send($client, json_encode(array('action' => 'joinChannel', 'status' => false, 'message' => 'No valid channel given ('.$data['query']['channel'].')!')));
            return true;
        }

        $this->users[$data['query']['id']]['lastActivity'] = time();

        $this->users[$data['query']['id']]['channels'][$data['query']['channel']] = true;

        $messages = array();
        if (isset($data['query']['messages'])) {
            list($messages, $newTimestamp) = $this->getMessages($data['query']['messages'] > 1 ? $data['query']['messages'] : 0, $data['query']['id'], array($data['query']['channel'] => true));
        }


        $this->addMessage($this->users[$data['query']['id']]['username'].' joined!', 'join', $data['query']['channel'], $data['query']['id']);
        $this->addMessage($this->channels[$data['query']['channel']], 'welcome', $data['query']['channel'], $data['query']['id'], $data['query']['id']);


        $server->send($client, json_encode(array('action' => 'joinChannel', 'status' => true, 'channel' => $data['query']['channel'], 'messages' => $messages, 'message' => 'You have joined the Chat (Channel '.htmlspecialchars($data['query']['channel']).')')));
        $this->sendMessages($server);

        return true;
    }

    public function getChannelList($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO LIST CHANNELS\n";
        if (empty($data['query']['id']) || !isset($this->users[$data['query']['id']])) {
            echo 'CLIENT '.$client['key']." IS NO VALID CHAT USER!\n";
            $server->send($client, json_encode(array('action' => 'channellist', 'status' => false, 'message' => 'No valid id given ('.(empty($data['query']['id']) ? 'no id' : $data['query']['id']).')!')));
            return true;
        }

        $this->users[$data['query']['id']]['lastActivity'] = time();

        $server->send($client, json_encode(array('action' => 'channellist', 'status' => true, 'channel' => isset($data['query']['channel']) ? $data['query']['channel'] : false, 'message' => count($this->channels).' Channels available', 'channellist' => array_keys($this->channels))));
        //$this->sendMessages($server);

        return true;
    }

    public function leaveChannel($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO LEAVE CHANNEL\n";
        if (empty($data['query']['id']) || !isset($this->users[$data['query']['id']])) {
            echo 'CLIENT '.$client['key']." IS NO VALID CHAT USER!\n";
            $server->send($client, json_encode(array('action' => 'leaveChannel', 'status' => false, 'message' => 'No valid id given ('.(empty($data['query']['id']) ? 'no id' : $data['query']['id']).')!')));
            return true;
        }
        if (empty($data['query']['channel']) || !isset($this->users[$data['query']['id']]['channels'][$data['query']['channel']])) {
            echo 'CLIENT '.$client['key']." IS NOT IN CHANNEL ".$data['query']['channel']."!\n";
            $server->send($client, json_encode(array('action' => 'leaveChannel', 'status' => false, 'message' => 'No valid channel given ('.$data['query']['channel'].')!')));
            return true;
        }

        $this->users[$data['query']['id']]['lastActivity'] = time();

        unset($this->users[$data['query']['id']]['channels'][$data['query']['channel']]);

        $this->addMessage($this->users[$data['query']['id']]['username'].' left!', 'left', $data['query']['channel'], $data['query']['id']);

        $server->send($client, json_encode(array('action' => 'leaveChannel', 'status' => true, 'channel' => $data['query']['channel'], 'message' => 'You have left (Channel '.htmlspecialchars($data['query']['channel']).')')));
        $this->sendMessages($server);

        return true;
    }

    public function getUserList($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO GET USER LIST\n";
        if (empty($data['query']['id']) || !isset($this->users[$data['query']['id']])) {
            echo 'CLIENT '.$client['key']." IS NO VALID CHAT USER!\n";
            $server->send($client, json_encode(array('action' => 'userlist', 'status' => false, 'message' => 'No valid id given ('.(empty($data['query']['id']) ? 'no id' : $data['query']['id']).')!')));
            return true;
        }
        if (!empty($data['query']['channel']) && (!isset($this->channels[$data['query']['channel']]) || !isset($this->users[$data['query']['id']]['channels'][$data['query']['channel']]))) {
            echo 'CLIENT '.$client['key']." IS NOT IN CHANNEL ".$data['query']['channel']."!\n";
            $server->send($client, json_encode(array('action' => 'userlist', 'status' => false, 'message' => 'No valid channel given ('.htmlspecialchars($data['query']['channel']).')!')));
            return true;
        }
        $channel = empty($data['query']['channel']) ? false : $data['query']['channel'];

        $users = array();
        foreach ($this->users as $user) {
            if (!$channel || isset($user['channels'][$channel])) {
                $users[] = $user['username'];
            }
        }
        sort($users);
        $server->send($client, json_encode(array('action' => 'userlist', 'status' => true, 'message' => count($users).' User online', 'channel' => $channel, 'userlist' => $users)));
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

    public function checkActivity($server)
    {
        foreach ($this->users as $userId => $user) {
            if (empty($user['waiting']) && $user['lastActivity'] < time() - 10) {
                echo 'DISCONNECTING USER '.$userId."\n";
                foreach ($user['channels'] as $channel => $desc) {
                    $this->addMessage($user['username'].' left!', 'left', $channel, $userId);
                }
                unset($this->users[$userId]);
            }
        }
        $this->sendMessages($server);
        return true;
        
    }

    protected function addMessage($message, $type, $channel, $from, $to = false)
    {
        //echo 'kein mongo';
        array_unshift($this->messages, array('time' => microtime(true), 'message' => $message, 'type' => $type, 'channel' => $channel, 'from' => $from, 'to' => $to));
        while (count($this->messages) > $this->maxMessages) {
            array_pop($this->messages);
        }
        //$this->sendMessages();
    }

    protected function getMessages($fromTime, $to = false, $channels = null)
    {
        $messages = array();
        foreach ($this->messages as $messageId => $message) {
            if ($message['time'] < (float) $fromTime) {
                break;
            }
            if ($channels !== null && !isset($channels[$message['channel']])) {
                continue;
            }
            if ($message['to'] == $to || $message['to'] == false) {
                $messageData = array('time' => $message['time'], 'type' => $message['type'], 'message' => $message['message'], 'channel' => $message['channel']);
                array_unshift($messages, $messageData);
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
        //$this->checkActivity($server);
        
        foreach ($this->users as $userId => $userData) {
            foreach ($userData['waiting'] as $connection => $timestamp) {
                list($newMessages, $newTimestamp) = $this->getMessages($timestamp, $userId, $userData['channels']);
                if (count($newMessages)) {
                    $this->users[$userId]['waiting'][$connection] = $newTimestamp + 0.0001;
                    echo 'SENDING MESSAGES TO '.$userId."\n";
                    $server->send($connection, json_encode(array('action' => 'get', 'status' => true, 'message' => count($newMessages).' new messages!', 'timestamp' => (float) $newTimestamp + 0.0001, 'messages' => $newMessages)));
                    //return;
                }
            }
        }
        
    }

    
    
}
