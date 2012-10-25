<?php

namespace nsqphp\Connection;

interface ConnectionInterface
{
    /**
     * Wait for readable
     * 
     * Waits for the socket to become readable (eg: have some data waiting)
     * 
     * @return boolean
     */
    public function isReadable();

    /**
     * Read from the socket exactly $len bytes
     *
     * @param integer $len How many bytes to read
     * 
     * @return string Binary data
    */
    public function read($len);
    
    /**
     * Write to the socket.
     *
     * @param string $buf The data to write
     */
    public function write($buf);
    
    /**
     * Get socket handle
     * 
     * @return Resource The socket
     */
    public function getSocket();
}