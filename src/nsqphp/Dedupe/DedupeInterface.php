<?php

namespace nsqphp\Dedupe;

use nsqphp\Message\MessageInterface;

interface DedupeInterface
{
    /**
     * Contains and add
     * 
     * Test if we have seen this message before, whilst also adding to our
     * knowledge the fact we have seen it now. We deduplicate against message
     * content, topic and channel (eg: all three have to be the same to consider
     * as a duplicate).
     * 
     * @param string $topic
     * @param string $channel
     * @param MessageInterface $msg
     * 
     * @return boolean
     */
    public function containsAndAdd($topic, $channel, MessageInterface $msg);
    
    /**
     * Remove knowledge of msg
     * 
     * Test if we have seen this message before and if we have (eg: if we still
     * have knowledge of the message) then "remove" it (so that we won't think
     * we have seen it).
     * 
     * @param string $topic
     * @param string $channel
     * @param MessageInterface $msg
     */
    public function erase($topic, $channel, MessageInterface $msg);
}