<?php

namespace nsqphp\Connection;

use nsqphp\Exception\LookupException;

/**
 * Represents nsqlookupd and allows us to find machines we need to talk to
 * for a given topic
 */
class Lookup
{
    /**
     * Hosts to connect to, incl. :port
     * 
     * @var array
     */
    private $hosts;
    
    /**
     * Connection timeout, in seconds
     * 
     * @var float
     */
    private $connectionTimeout;
    
    /**
     * Response timeout
     * 
     * @var float
     */
    private $responseTimeout;
    
    /**
     * Constructor
     * 
     * @param string|array $hosts Single host:port, many host:port with commas,
     *      or an array of host:port, of nsqlookupd servers to talk to
     *      (will default to localhost)
     * @param float $connectionTimeout In seconds
     * @param float $responseTimeout In seconds
     */
    public function __construct($hosts = NULL, $connectionTimeout = 1, $responseTimeout = 2)
    {
        if ($hosts === NULL) {
            $this->hosts = array('localhost:4161');
        } elseif (is_array($hosts)) {
            $this->hosts = $hosts;
        } else {
            $this->hosts = explode(',', $hosts);
        }
        $this->connectionTimeout = $connectionTimeout;
        $this->responseTimeout = $responseTimeout;
    }
    
    /**
     * Lookup hosts for a given topic
     * 
     * @param string $topic
     * 
     * @throws LookupException If we cannot talk to / get back invalid response
     *      from nsqlookupd
     * 
     * @return array
     */
    public function lookupHosts($topic)
    {
        $lookupHosts = array();
        
        foreach ($this->hosts as $host) {
            $url = "http://{$host}/lookup?topic=" . urlencode($topic);
            $ch = curl_init($url);
            $options = array(
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_HEADER         => FALSE,
                CURLOPT_FOLLOWLOCATION => FALSE,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => 'nsqphp',
                CURLOPT_CONNECTTIMEOUT => $this->connectionTimeout,
                CURLOPT_TIMEOUT        => $this->responseTimeout,
                CURLOPT_FAILONERROR    => TRUE
                );
            curl_setopt_array($ch, $options);
            $r = curl_exec($ch);
            $r = json_decode($r, TRUE);
            
            if (!is_array($r)) {
                throw new LookupException(
                        "Error talking to nsqlookupd via $url"
                        );
            }
            
            $producers = isset($r['data'], $r['data']['producers']) ? $r['data']['producers'] : array();
            foreach ($producers as $prod) {
                $h = "{$prod['address']}:{$prod['tcp_port']}";
                if (!in_array($h, $lookupHosts)) {
                    $lookupHosts[] = $h;
                }
                
            }
        }

        return $lookupHosts;
    }
}