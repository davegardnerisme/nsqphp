<?php

namespace nsqphp\Wire;

use nsqphp\Connection\ConnectionInterface;
use nsqphp\Message\Message;
use nsqphp\Exception\SocketException;
use nsqphp\Exception\ReadException;
use nsqphp\Exception\ErrorFrameException;
use nsqphp\Exception\ResponseFrameException;
use nsqphp\Exception\UnknownFrameException;

class Reader
{
    /**
     * Frame types
     */
    const FRAME_TYPE_RESPONSE = 0;
    const FRAME_TYPE_ERROR = 1;
    const FRAME_TYPE_MESSAGE = 2;
    
    /**
     * Read response
     * 
     * @param ConnectionInterface $connection
     */
    public function readResponse(ConnectionInterface $connection)
    {
        try {
            $size = $this->readInt($connection);
            $response = $this->readString($connection, $size);
        } catch (SocketException $e) {
            throw new ReadException("Error reading response [$size]", NULL, $e);
        }
        return $response;
    }
    
    /**
     * Read message
     * 
     * @param ConnectionInterface $connection
     * 
     * @throws ErrorFrameException If we receive an error frame
     * @throws ResponseFrameException If we receive a response frame
     */
    public function readMessage(ConnectionInterface $connection)
    {
        try {
            $size = $frameType = NULL;
            $size = $this->readInt($connection);
            $frameType = $this->readInt($connection);
        } catch (SocketException $e) {
            throw new ReadException("Error reading message frame [$size, $frameType]", NULL, $e);
        }

        switch ($frameType) {
            case self::FRAME_TYPE_RESPONSE:
                throw new ResponseFrameException($this->readString($connection, $size-4));
                break;
            case self::FRAME_TYPE_ERROR:
                throw new ErrorFrameException($this->readString($connection, $size-4));
                break;
            case self::FRAME_TYPE_MESSAGE:
                try {
                    $ts = $attempts = $id = $msgPayload = NULL;
                    $ts = $this->readLong($connection);
                    $attempts = $this->readShort($connection);
                    $id = $this->readString($connection, 16);
                    $msgPayload = $this->readString($connection, $size - 30);
                } catch (SocketException $e) {
                    throw new ReadException("Error reading message details [$ts, $attempts, $id, $msgPayload]", NULL, $e);
                }
                
                $msg = new Message($msgPayload, $id, $attempts, $ts);
                
                break;
            default:
                throw new UnknownFrameException($this->readString($connection, $size-8));
                break;
        }

        return $msg;
    }
    
    /**
     * Read frame
     * 
     * @return array With keys: type, data
     */
    public function readFrame(ConnectionInterface $connection)
    {
        $frameType = $size = $data = NULL;
        try {
            //$frameType = $this->readInt($connection);
            $size = $this->readInt($connection);
            $data = $this->readString($connection, $size);
            return array(
                'type'  => $frameType,
                'data'  => $data
                );
        } catch (SocketException $e) {
            throw new ReadException("Error reading frame [$frameType, $size]", NULL, $e);
        }
    }
    
    /**
     * Read and unpack short integer (2 bytes) from connection
     *
     * @param ConnectionInterface $connection
     * 
     * @return integer
     */
    private function readShort(ConnectionInterface $connection)
    {
        list(,$res) = unpack('n', $connection->read(2));
        return $res;
    }
    
    /**
     * Read and unpack integer (4 bytes) from connection
     *
     * @param ConnectionInterface $connection
     * 
     * @return integer
     */
    private function readInt(ConnectionInterface $connection)
    {
        list(,$res) = unpack('N', $connection->read(4));
        if ((PHP_INT_SIZE !== 4)) {
            $res = sprintf("%u", $res);
        }
        return (int)$res;
    }

    /**
     * Read and unpack long (8 bytes) from connection
     *
     * @param ConnectionInterface $connection
     * 
     * @return string We return as string so it works on 32 bit arch
     */
    private function readLong(ConnectionInterface $connection)
    {
        $hi = unpack('N', $connection->read(4));
        $lo = unpack('N', $connection->read(4));

        // workaround signed/unsigned braindamage in php
        $hi = sprintf("%u", $hi[1]);
        $lo = sprintf("%u", $lo[1]);

        return bcadd(bcmul($hi, "4294967296" ), $lo);
    }

    /**
     * Read and unpack string; reading $size bytes
     *
     * @param ConnectionInterface $connection
     * @param integer $size
     * 
     * @return string 
     */
    private function readString(ConnectionInterface $connection, $size)
    {
        $temp = unpack("c{$size}chars", $connection->read($size));
        $out = ""; 
        foreach($temp as $v) {
            if ($v > 0) {
                $out .= chr($v);
            }
        }
        return $out; 
    }
}
