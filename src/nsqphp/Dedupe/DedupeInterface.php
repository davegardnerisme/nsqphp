<?php

namespace nsqphp\Dedupe;

use nsqphp\Message\MessageInterface;

interface DedupeInterface
{
    /**
     * Contains and add
     * 
     * Test if we have seen this message before, whilst also adding to our
     * knowledge the fact we have seen it now.
     * 
     * @param MessageInterface $msg
     * 
     * @return boolean
     */
    public function containsAndAdd(MessageInterface $msg);
}