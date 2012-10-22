<?php

namespace nsqphp\RequeueStrategy;

use nsqphp\Message\MessageInterface;

interface RequeueStrategyInterface
{
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
    public function shouldRequeue(MessageInterface $msg);
}