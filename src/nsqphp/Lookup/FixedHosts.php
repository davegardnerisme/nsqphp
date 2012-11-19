<?php

namespace nsqphp\Lookup;

use nsqphp\Exception\LookupException;

/**
 * Lookup implementation that just returns a fixed set of hosts
 */
class FixedHosts implements LookupInterface
{
    /**
     * NSQD hosts to connect to, incl. :port
     * 
     * @var array
     */
    private $hosts;
    
    /**
     * Constructor
     * 
     * @param string|array $hosts Single host:port, many host:port with commas,
     *      or an array of host:port, of nsqd servers
     */
    public function __construct($hosts = NULL)
    {
        if ($hosts === NULL) {
            $this->hosts = array('localhost:4160');
        } elseif (is_array($hosts)) {
            $this->hosts = $hosts;
        } else {
            $this->hosts = explode(',', $hosts);
        }
    }
    
    /**
     * Lookup hosts for a given topic
     * 
     * @param string $topic
     * 
     * @throws LookupException If we cannot talk to / get back invalid response
     *      from nsqlookupd
     * 
     * @return array Should return array [] = host:port
     */
    public function lookupHosts($topic)
    {
        return $this->hosts;
    }
}