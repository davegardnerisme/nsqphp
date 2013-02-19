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
     * Heartbeat response content
     */
    const HEARTBEAT = '_heartbeat_';
    
    /**
     * OK response content
     */
    const OK = 'OK';

    /**
     * Read frame
     * 
     * @throws ReadException If we have a problem reading the core frame header
     *      (data size + frame type)
     * @throws ReadException If we have a problem reading the frame data
     * 
     * @return array With keys: type, data
     */
    public function readFrame(ConnectionInterface $connection)
    {
        try {
            $size = $frameType = NULL;
            $size = $this->readInt($connection);
            $frameType = $this->readInt($connection);
        } catch (SocketException $e) {
            throw new ReadException("Error reading message frame [$size, $frameType]", NULL, $e);
        }

        $frame = array(
            'type'  => $frameType,
            'size'  => $size
            );
        
        try {
            switch ($frameType) {
                case self::FRAME_TYPE_RESPONSE:
                    $frame['response'] = $this->readString($connection, $size-4);
                    break;
                case self::FRAME_TYPE_ERROR:
                    $frame['error'] = $this->readString($connection, $size-4);
                    break;
                case self::FRAME_TYPE_MESSAGE:
                    $frame['ts'] = $this->readLong($connection);
                    $frame['attempts'] = $this->readShort($connection);
                    $frame['id'] = $this->readString($connection, 16);
                    $frame['payload'] = $this->readString($connection, $size - 30);
                    break;
                default:
                    throw new UnknownFrameException($this->readString($connection, $size-4));
                    break;
            }
        } catch (SocketException $e) {
            throw new ReadException("Error reading frame details [$size, $frameType]", NULL, $e);
        }

        return $frame;
    }
    
    /**
     * Test if frame is a response frame (optionally with content $response)
     *
     * @param array $frame
     * @param string|NULL $response If provided we'll check for this specific
     *      response
     * 
     * @return boolean
     */
    public function frameIsResponse(array $frame, $response = NULL)
    {
        return isset($frame['type'], $frame['response'])
                && $frame['type'] === self::FRAME_TYPE_RESPONSE
                && ($response === NULL || $frame['response'] === $response);
    }

    /**
     * Test if frame is a message frame
     *
     * @param array $frame
     * 
     * @return boolean
     */
    public function frameIsMessage(array $frame)
    {
        return isset($frame['type'], $frame['payload'])
                && $frame['type'] === self::FRAME_TYPE_MESSAGE;
    }
    
    /**
     * Test if frame is heartbeat
     * 
     * @param array $frame
     * 
     * @return boolean
     */
    public function frameIsHeartbeat(array $frame)
    {
        return $this->frameIsResponse($frame, self::HEARTBEAT);
    }

    /**
     * Test if frame is OK
     * 
     * @param array $frame
     * 
     * @return boolean
     */
    public function frameIsOk(array $frame)
    {
        return $this->frameIsResponse($frame, self::OK);
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
