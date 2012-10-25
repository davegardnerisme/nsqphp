<?php

namespace nsqphp\Connection;

use nsqphp\Exception\ConnectionException;
use nsqphp\Exception\SocketException;

/**
 * Represents a pool of connections to one or more NSQD servers
 */
class ConnectionPool implements \Iterator, \Countable
{
    /**
     * Connections
     * 
     * @var array [(string)$connection] = $connection
     */
    private $connections = array();

    /**
     * Add connection
     * 
     * @param ConnectionInterface $connection
     */
    public function add(ConnectionInterface $connection)
    {
        if (isset($this->connections[(string)$connection])) {
            throw new \InvalidArgumentException(
                    'We already have a connection to this server in the pool.'
                    );
        }
        $this->connections[(string)$connection] = $connection;
    }
    
    /**
     * Establish connection and add
     * 
     * This will create connection objects from hosts.
     * 
     * @param string|array $hosts Either a single host:port, a string of many
     *      host:port with commas, or an array of host:port
     *      (NB: actually :port is optional)
     * @param float|NULL $connectionTimeout Optional timeout in seconds
     *      (no need to be whole numbers)
     * @param float|NULL $readWriteTimeout Optional Socket timeout during active
     *      read/write in seconds (no need to be whole numbers)
     * @param float|NULL $readWaitTimeout Optional timeout - how long we'll wait
     *      for data to become available before giving up (eg; duirng SUB loop)
     *      In seconds (no need to be whole numbers)
     */
    public function createAndAdd($hosts, $connectionTimeout = NULL, $readWriteTimeout = NULL, $readWaitTimeout = NULL)
    {
        if (!is_array($hosts)) {
            $hosts = explode(',', $hosts);
        }
        foreach ($hosts as $host) {
            $parts = explode(':', $host);
            $conn = new Connection(
                    $parts[0],
                    isset($parts[1]) ? $parts[1] : NULL,
                    $connectionTimeout,
                    $readWriteTimeout,
                    $readWaitTimeout
                    );
            if (!$this->hasConnection($conn)) {
                $this->add($conn);
            }
        }
    }
    
    /**
     * Test if has connection
     * 
     * Remember that the sockets are lazy-initialised so we can create
     * connection instances to test with without incurring a socket connection.
     * 
     * @param ConnectionInterface $connection
     * 
     * @return boolean
     */
    public function hasConnection(ConnectionInterface $connection)
    {
        return isset($this->connections[(string)$connection]);
    }
    
    /**
     * Find connection from socket
     * 
     * @param Resource $socket
     * 
     * @return ConnectionInterface|NULL Will return NULL if not found
     */
    public function find($socket)
    {
        foreach ($this->connections as $conn) {
            if ($conn->getSocket() === $socket) {
                return $conn;
            }
        }
        return NULL;
    }
    
    /**
     * Get key of current item as string
     *
     * @return string
     */
    public function key()
    {
        return key($this->connections);
    }

    /**
     * Test if current item valid
     *
     * @return boolean
     */
    public function valid()
    {
        return (current($this->connections) === FALSE) ? FALSE : TRUE;
    }

    /**
     * Fetch current value
     *
     * @return mixed
     */
    public function current()
    {
        return current($this->connections);
    }

    /**
     * Go to next item
     */
    public function next()
    {
        next($this->connections);
    }

    /**
     * Rewind to start
     */
    public function rewind()
    {
        reset($this->connections);
    }

    /**
     * Move to end
     */
    public function end()
    {
        end($this->connections);
    }

    /**
     * Get count of items
     *
     * @return integer
     */
    public function count()
    {
        return count($this->connections);
    }
}