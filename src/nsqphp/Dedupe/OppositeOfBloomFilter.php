<?php

namespace nsqphp\Dedupe;

use nsqphp\Message\MessageInterface;

/**
 * Lossy hash map that is able to tell us whether we have seen a message
 * before, with a change of a false negative (eg: saying we haven't, when we
 * have), but no change of a false positive (eg: saying we have when we
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
 * http://somethingsimilar.com/2012/05/21/the-opposite-of-a-bloom-filter/
 */
class OppositeOfBloomFilter implements DedupeInterface
{
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
     * Collisions
     * 
     * @var integer
     */
    private $collisions = array();

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
     * knowledge the fact we have seen it now.
     * 
     * @param MessageInterface $msg
     * 
     * @return boolean
     */
    public function containsAndAdd(MessageInterface $msg)
    {
        $element = $msg->getPayload();
        $hash = hash('adler32', $element, TRUE);
        list(, $val) = unpack('N', $hash);
        $index = $val % $this->size;
        $content = hash('sha256', $element);
        $seen = isset($this->map[$index]) && $this->map[$index] === $content;
        if (!isset($this->collisions[$index])) {
            $this->collisions[$index] = 0;
        } else {
            $this->collisions[$index]++;
        }
        $this->map[$index] = $content;
        return $seen;
    }
    
    /**
     * Get hash collisions
     * 
     * @return array
     */
    public function getHashCollisions()
    {
        ksort($this->collisions);
        return $this->collisions;
    }
}