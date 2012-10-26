<?php

namespace nsqphp\Dedupe;

use nsqphp\Message\MessageInterface;

/**
 * Lossy hash map that is able to tell us whether we have seen a message
 * before, with a change of a false negative (eg: saying we haven't, when we
 * have), but no change of a false positive (eg: saying we have when we
 * haven't).
 * 
 * Stores the hash map in Memcached, allowing us to share this memory space
 * between N processes and keep it alive if the PHP process is killed.
 * NB: fails silently so will happily not dedupe anything if Memcached dead.
 * 
 * This actually uses a hash of the content, so theoretically if both hash
 * functions collided (eg: the one to pick index and the one to hash content)
 * then we would return a false positive. I'm assuming this is vanishingly
 * small. This should probably be investigated some more.
 * 
 * http://somethingsimilar.com/2012/05/21/the-opposite-of-a-bloom-filter/
 */
class OppositeOfBloomFilterMemcached implements DedupeInterface
{
    /**
     * Memcached instance
     * 
     * @var \Memcached
     */
    private $memcached;
    
    /**
     * Size of hash map
     * 
     * @var integer
     */
    private $size;
    
    /**
     *
     * @param integer $size
     * @param string|array $hosts Single host, many hosts with commas, or array
     *      of hosts -- which Memcached server(s) to connect to; default localhost
     */
    public function __construct($size = 1000000, $hosts = 'localhost')
    {
        $this->size = $size;

        $this->memcached = new \Memcached;
        if (!is_array($hosts)) {
            $hosts = explode(',', $hosts);
        }
        $servers = array();
        foreach ($hosts as $host) {
            $servers[] = array($host, 11211, 100);
        }
        $this->memcached->addServers($servers);
        $this->memcached->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 100);
        $this->memcached->setOption(\Memcached::OPT_SEND_TIMEOUT, 50);
        $this->memcached->setOption(\Memcached::OPT_RECV_TIMEOUT, 50);
        $this->memcached->setOption(\Memcached::OPT_POLL_TIMEOUT, 250);
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
        $element = "$topic:$channel:" . $msg->getPayload();
        $hash = hash('adler32', $element, TRUE);
        list(, $val) = unpack('N', $hash);
        $index = $val % $this->size;
        $content = md5($element);
        
        $mcKey = "nsqphp:{$this->size}:{$index}";
        $storedContentHash = $this->memcached->get($mcKey);
        $seen = $storedContentHash && $storedContentHash === $content;
        $this->memcached->set($mcKey, $content);
        return $seen;
    }
}