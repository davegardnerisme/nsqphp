<?php

namespace nsqphp\Message;

class Message implements MessageInterface
{
    /**
     * Construct from frame
     * 
     * @param array $frame
     */
    public static function fromFrame(array $frame)
    {
        return new Message(
                $frame['payload'],
                $frame['id'],
                $frame['attempts'],
                $frame['ts']
                );
    }
    
    /**
     * Message payload - string
     * 
     * @var string
     */
    private $data = '';
    
    /**
     * Message ID; if relevant
     * 
     * @var string|NULL
     */
    private $id = NULL;
    
    /**
     * How many attempts have been made; if relevant
     * 
     * @var integer|NULL
     */
    private $attempts = NULL;
    
    /**
     * Timestamp - UNIX timestamp in seconds (incl. fractions); if relevant
     * 
     * @var float|NULL
     */
    private $ts = NULL;
    
    /**
     * Constructor
     * 
     * @param string $data
     * @param string|NULL $id The message ID in hex (as ASCII)
     * @param integer|NULL $attempts How many attempts have been made on msg so far
     * @param float|NULL $ts Timestamp (nanosecond precision, as number of seconds)
     */
    public function __construct($data, $id = NULL, $attempts = NULL, $ts = NULL)
    {
        $this->data = $data;
        $this->id = $id;
        $this->attempts = $attempts;
        $this->ts = $ts;
    }
    
    /**
     * Get message payload
     * 
     * @return string
     */
    public function getPayload()
    {
        return $this->data;
    }

    /**
     * Get message ID
     * 
     * @return string|NULL
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Get attempts
     * 
     * @return integer|NULL
     */
    public function getAttempts()
    {
        return $this->attempts;
    }
    
    /**
     * Get timestamp
     * 
     * @return float|NULL
     */
    public function getTimestamp()
    {
        return $this->ts;
    }
}