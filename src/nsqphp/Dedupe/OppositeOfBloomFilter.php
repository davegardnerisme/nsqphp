<?php

namespace nsqphp\Dedupe;

use nsqphp\Message\MessageInterface;

/**
 * Lossy hash map that is able to tell us whether we have seen a message
 * before, with a chance of a false negative (eg: saying we haven't, when we
 * have), but no chance of a false positive (eg: saying we have when we
 * haven't).
 * 
 * Stores the hash map internally as a PHP array; hence bound within a
 * single specific process (see Memcached implementation for one that uses
 * external memory storage).
 * 
 * This actually uses a hash of the content, so theoretically if both hash
 * functions collided (eg: the one to pick index and the one to hash content)
 * then we would return a false positive. I'm assuming this is vanishingly
 * small. This should probably be investigated some more.
 * 
 * May contain cruft.
 * 
 * http://somethingsimilar.com/2012/05/21/the-opposite-of-a-bloom-filter/
 */
class OppositeOfBloomFilter implements DedupeInterface
{
    /**
     * Deleted placeholder
     */
    const DELETED = 'D';
    
    /**
     * Hash map
     * 
     * @var array
     */
    private $map = array();
    
    /**
     * Size of hash map
     * 
     * @var integer
     */
    private $size;
    
    /**
     *
     * @param integer $size
     */
    public function __construct($size = 100000)
    {
        $this->size = $size;
    }
    
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
    public function containsAndAdd($topic, $channel, MessageInterface $msg)
    {
        $hashed = $this->hash($topic, $channel, $msg);
        $this->map[$hashed['index']] = $hashed['content'];
        return $hashed['seen'];
    }
    
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
    public function erase($topic, $channel, MessageInterface $msg)
    {
        if ($hashed['seen']) {
            $this->map[$hashed['index']] = self::DELETED;
        }
    }
    
    /**
     * Get bucket / content hash
     * 
     * @param string $topic
     * @param string $channel
     * @param MessageInterface $msg
     * 
     * @return array index, content, seen (boolean)
     */
    private function hash($topic, $channel, MessageInterface $msg)
    {
        $element = "$topic:$channel:" . $msg->getPayload();
        $hash = hash('adler32', $element, TRUE);
        list(, $val) = unpack('N', $hash);
        $index = $val % $this->size;
        $content = md5($element);
        $seen = isset($this->map[$index]) && $this->map[$index] === $content;
        return array('index' => $index, 'content' => $content, 'seen' => $seen);
    }
}
