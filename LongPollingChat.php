<?php
class LongPollingChat {
    private $users = array();
    private $connections = array();

    public function join($server, $client, $data)
    {
        echo 'CLIENT '.$client['key']." TRIES TO JOIN CHAT\n";
        if (empty($data['data']['username'])) {
            $server->send($client, json_encode(array('status' => false, 'message' => 'No Username given!')));
            return;
        }
        $userId = md5($data['data']['username'].rand(10000,99999));
        $this->users[$userId] = array('joined' => time(), 'lastActivity' => time(), 'connections' => array($client['key'] => true));
        $this->connections[$client['key']] = $userId;
        echo 'USER '.$userId.' JOINED CHAT!'."\n";
        $server->send($client, json_encode(array('status' => true, 'message' => 'You have joined the Chat', 'id' => $userId)));
    }

    public function disconnect($server, $client)
    {
        if (isset($this->connections[$client['key']])) {
            $userId = $this->connections[$client['key']];
            unset($this->connections[$client['key']]);
            unset($this->users[$userId]['connections'][$client['key']]);
            $this->users[$userId]['lastActivity'] = time();
            echo 'REMOVING CONNECTION '.$client['key'].' FROM CHAT USER '.$userId."\n";
        }
    }
}
