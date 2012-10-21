<?php

namespace nsqphp\Message;

interface MessageInterface
{
    /**
     * Get message payload
     * 
     * @return string
     */
    public function getPayload();
}