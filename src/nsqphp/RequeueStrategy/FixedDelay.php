<?php

namespace nsqphp\RequeueStrategy;

use nsqphp\Message\MessageInterface;

/**
 * Fixed delay requeue strategy
 * 
 * Retry all failed messages N times with X delay.
 */
class FixedDelay implements RequeueStrategyInterface
{
    /**
     * Number of attempts to make
     * 
     * @var integer
     */
    private $maxAttempts;
    
    /**
     * How long to delay for
     * 
     * @var integer
     */
    private $delay;
    
    /**
     * Constructor
     * 
     * @param integer $maxAttempts
     * @param integer $delay
     */
    public function __construct($maxAttempts = 10, $delay = 50)
    {
        $this->maxAttempts = $maxAttempts;
        $this->delay = $delay;
        
        if (!is_integer($this->delay) || $this->delay < 0) {
            throw new \InvalidArgumentException(
                    '"delay" invalid; must be integer value >= 0'
                    );
        }
    }
    
    /**
     * Test if should requeue and with what delay
     * 
     * The message will contain how many attempts had been made _before_ we
     * made our attempt (which must have failed).
     * 
     * @param MessageInterface $msg
     * 
     * @return integer|NULL The number of milliseconds to delay for, if we 
     *      want to retry, or NULL to drop it on the floor
     */
    public function shouldRequeue(MessageInterface $msg)
    {
        $attempts = $msg->getAttempts();
        return $attempts + 1 < $this->maxAttempts
                ? $this->delay
                : NULL;
    }
}