<?php

namespace nsqphp\Connection;

use nsqphp\Exception\ConnectionException;
use nsqphp\Exception\SocketException;

/**
 * Represents a single connection to a single NSQD server
 */
class Connection implements ConnectionInterface
{
    /**
     * Hostname
     * 
     * @var string
     */
    private $hostname;
    
    /**
     * Port number
     * 
     * @var integer
     */
    private $port;
    
    /**
     * Connection timeout - in seconds
     * 
     * @var float
     */
    private $connectionTimeout;
    
    /**
     * Read/write timeout - in whole seconds
     * 
     * @var integer
     */
    private $readWriteTimeoutSec;
    
    /**
     * Read/write timeout - in whole microseconds
     * 
     * (to be added to the whole seconds above)
     * 
     * @var integer
     */
    private $readWriteTimeoutUsec;

    /**
     * Read wait timeout - in whole seconds
     * 
     * @var integer
     */
    private $readWaitTimeoutSec;

    /**
     * Read wait timeout - in whole microseconds
     * 
     * (to be added to the whole seconds above)
     * 
     * @var integer
     */
    private $readWaitTimeoutUsec;
    
    /**
     * Non-blocking mode?
     * 
     * @var boolean
     */
    private $nonBlocking;
    
    /**
     * Optional on-connect callback
     * 
     * @var callable|NULL
     */
    private $connectCallback;

    /**
     * Socket handle
     * 
     * @var Resource|NULL
     */
    private $socket = NULL;
    
    /**
     * Constructor
     * 
     * @param string $hostname Default localhost
     * @param integer $port Default 4150
     * @param float $connectionTimeout In seconds (no need to be whole numbers)
     * @param float $readWriteTimeout Socket timeout during active read/write
     *      In seconds (no need to be whole numbers)
     * @param float $readWaitTimeout How long we'll wait for data to become
     *      available before giving up (eg; duirng SUB loop)
     *      In seconds (no need to be whole numbers)
     * @param boolean $nonBlocking Put socket in non-blocking mode
     * @param callable|NULL $connectCallback Optional on-connect callback (will
     *      be called whenever we establish a connection)
     */
    public function __construct(
            $hostname = 'localhost',
            $port = 4150,
            $connectionTimeout = 3,
            $readWriteTimeout = 3,
            $readWaitTimeout = 15,
            $nonBlocking = FALSE,
            $connectCallback = NULL
            ) {
        $this->hostname = $hostname;
        $this->port = $port;
        $this->connectionTimeout = $connectionTimeout;
        $this->readWriteTimeoutSec = floor($readWriteTimeout);
        $this->readWriteTimeoutUsec = ($readWriteTimeout - $this->readWriteTimeoutSec) * 1000000;
        $this->readWaitTimeoutSec = floor($readWaitTimeout);
        $this->readWaitTimeoutUsec = ($readWaitTimeout - $this->readWaitTimeoutSec) * 1000000;
        $this->nonBlocking = (bool)$nonBlocking;
        $this->connectCallback = $connectCallback;
    }
    
    /**
     * Wait for readable
     * 
     * Waits for the socket to become readable (eg: have some data waiting)
     * 
     * @return boolean
     */
    public function isReadable()
    {
        $read = array($socket = $this->getSocket());
        $readable = stream_select($read, $null, $null, $this->readWaitTimeoutSec, $this->readWaitTimeoutUsec);
        return $readable ? TRUE : FALSE;
    }
    
    /**
     * Read from the socket exactly $len bytes
     *
     * @param integer $len How many bytes to read
     * 
     * @return string Binary data
    */
    public function read($len)
    {
        $null = NULL;
        $read = array($socket = $this->getSocket());
        $buffer = $data = '';
        while (strlen($data) < $len) {
            $readable = stream_select($read, $null, $null, $this->readWriteTimeoutSec, $this->readWriteTimeoutUsec);
            if ($readable > 0) {
                $buffer = stream_socket_recvfrom($socket, $len);
                if ($buffer === FALSE) {
                    throw new SocketException("Could not read {$len} bytes from {$this->hostname}:{$this->port}");
                } else if ($buffer == '') {
                    throw new SocketException("Read 0 bytes from {$this->hostname}:{$this->port}");
                }
            } else if ($readable === 0) {
                throw new SocketException("Timed out reading {$len} bytes from {$this->hostname}:{$this->port}");
            } else {
                throw new SocketException("Could not read {$len} bytes from {$this->hostname}:{$this->port}");
            }
            $data .= $buffer;
            $len -= strlen($buffer);
        }
        return $data;
    }

    /**
     * Write to the socket.
     *
     * @param string $buf The data to write
     */
    public function write($buf)
    {
        $null = NULL;
        $write = array($socket = $this->getSocket());

        // keep writing until all the data has been written
        while (strlen($buf) > 0) {
            // wait for stream to become available for writing
            $writable = stream_select($null, $write, $null, $this->readWriteTimeoutSec, $this->readWriteTimeoutUsec);
            if ($writable > 0) {
                // write buffer to stream
                $written = stream_socket_sendto($socket, $buf);
                if ($written === -1 || $written === FALSE) {
                    throw new SocketException("Could not write " . strlen($buf) . " bytes to {$this->hostname}:{$this->port}");
                }
                // determine how much of the buffer is left to write
                $buf = substr($buf, $written);
            } else if ($writable === 0) {
                throw new SocketException("Timed out writing " . strlen($buf) . " bytes to {$this->hostname}:{$this->port}");
            } else {
                throw new SocketException("Could not write " . strlen($buf) . " bytes to {$this->hostname}:{$this->port}");
            }
        }
    }
    
    /**
     * Get socket handle
     * 
     * @return Resource The socket
     */
    public function getSocket()
    {
        if ($this->socket === NULL) {
            $this->socket = fsockopen($this->hostname, $this->port, $errNo, $errStr, $this->connectionTimeout);
            if ($this->socket === FALSE) {
                throw new ConnectionException(
                        "Could not connect to {$this->hostname}:{$this->port} ({$errStr} [{$errNo}])"
                        );
            }
            if ($this->nonBlocking) {
                stream_set_blocking($this->socket, 0);
            }
            
            // on-connection callback
            if ($this->connectCallback !== NULL) {
                call_user_func($this->connectCallback, $this);
            }
        }
        return $this->socket;
    }
    
    /**
     * To string (for debug logging)
     * 
     * @return string
     */
    public function __toString()
    {
        return "{$this->hostname}:{$this->port}";
    }
}