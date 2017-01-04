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
        $message = new static;
        foreach (array('id', 'attempts', 'ts') as $k) {
            $message->$k = $frame[$k];
        }
        foreach (json_decode($frame['payload'], true) as $k => $v) {
            $message->$k = $v;
        }
        return $message;
    }
    
    /**
     * Message user data - string
     * 
     * @var string
     */
    private $data = '';

    /**
     * Message schedule timestamp
     *
     * @var int
     */
    private $scheduledAt = NULL;

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
     * @param int $delay Delay in second
     */
    public function __construct($data = '', $delay = 0)
    {
        $this->data = $data;
        if ($delay) {
            $this->scheduledAt = time() + $delay;
        }
    }

    /**
     * Get message user data
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get message schedule timestamp (if delayed, otherwise null)
     *
     * @return int|null
     */
    public function getScheduledAt()
    {
        return $this->scheduledAt;
    }

    /**
     * Get message delay in second
     *
     * @return int
     */
    public function getDelay()
    {
        return max(0, $this->scheduledAt - time());
    }

    /**
     * Whether the message is delayed
     *
     * @return boolean
     */
    public function isDelayed()
    {
        return (bool) $this->scheduledAt;
    }

    /**
     * Whether the message must be delayed instead of being processed
     *
     * @return boolean
     */
    public function mustBeDelayed()
    {
        return $this->isDelayed() && $this->attempts == 1;
    }

    /**
     * Get ready timestamp
     *
     * @return int
     */
    public function getReadyAt()
    {
        if ($this->isDelayed()) {
            return $this->getScheduledAt();
        }
        return (int) $this->getTimestamp();
    }
    
    /**
     * Get message payload
     * 
     * @return string
     */
    public function getPayload()
    {
        return json_encode(array(
            'data' => $this->data,
            'scheduledAt' => $this->scheduledAt,
        ));
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
        if (!$this->attempts) {
            return null;
        }
        if ($this->isDelayed()) {
            return max(1, $this->attempts - 1);
        }
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