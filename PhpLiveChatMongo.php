<?php
class PhpLiveChatMongo extends PhpLiveChat {


    private $db = null;

    private $collection = null;

    public function setDatabase($db)
    {
        $this->db = $db;
        $this->collection = $this->db->messages;
        $this->collection->ensureIndex( array( "time" => 1, 'channel' => 1, 'to' => 1 ) );
    }

    protected function addMessage($message, $type, $channel, $from, $to = false)
    {
        //echo 'JA, mongo :)';
        $doc = array(
            'time' => microtime(true),
            'message' => $message,
            'type' => $type,
            'channel' => $channel,
            'from' => $from,
            'to' => $to
        );

        $this->collection->insert($doc);

        //$this->sendMessages();
    }

    protected function getMessages($fromTime, $to = false, $channels = null)
    {
        $query = array('time' => array('$gte' => (float) $fromTime), 'to' => array('$in' => array($to, false)));
        if ($channels !== null) {
            $query['channel'] = array('$in' => array_keys($channels));
        }

        //var_dump($query);

        $cursor = $this->collection
                    ->find($query, array('time' => 1, 'message' => 1, 'type' => 1, 'channel' => 1))
                    ->sort(array('time' => -1))->limit(100);
        
        $messages = array();
        while( $cursor->hasNext() ) {
            $messages[] = $cursor->getNext();
        }
        
        $messages = array_reverse($messages);

        if ($last = end($messages)) {
            $last = $last['time'];
        } else {
            $last = $fromTime;
        }
        return array($messages, $last);
    }

}
