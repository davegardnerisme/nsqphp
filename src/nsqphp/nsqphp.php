<?php

namespace nsqphp;

use nsqphp\Connection\ConnectionInterface;
use nsqphp\Logger\LoggerInterface;
use nsqphp\Message\MessageInterface;

class nsqphp
{
    /**
     * Heartbeat
     */
    const HEARTBEAT = '_heartbeat_';
    
    /**
     * Connection
     * 
     * @var ConnectionInterface
     */
    private $connection;
    
    /**
     * Logger, if any enabled
     * 
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * Wire reader
     * 
     * @var Wire\Reader
     */
    private $reader;
    
    /**
     * Wire writer
     * 
     * @var Wire\Writer
     */
    private $writer;
    
    /**
     * Constructor
     * 
     * @param ConnectionInterface $connection
     * @param LoggerInterface|NULL $logger
     */
    public function __construct(ConnectionInterface $connection, LoggerInterface $logger = NULL)
    {
        $this->connection = $connection;
        $this->logger = $logger;
        
        $this->reader = new Wire\Reader;
        $this->writer = new Wire\Writer;
        
        // @todo
        $this->shortId = 'foo';
        $this->longId = 'foo.bar';
        
        // say hello
        $this->connection->write($this->writer->magic());

        if ($this->logger) {
            $this->logger->info("nsqphp initialised");
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        // say goodbye
        $this->connection->write($this->writer->close());
        if ($this->logger) {
            $this->logger->info("nsqphp closing");
        }
    }
    
    /**
     * Publish message
     *
     * @param string $topic A valid topic name: [.a-zA-Z0-9_-] and 1 < length < 32
     * @param MessageInterface $msg
     * 
     * @throws Exception\ProtocolException If we don't get "OK" back from server
     */
    public function publish($topic, MessageInterface $msg)
    {
        $this->connection->write($this->writer->publish($topic, $msg->getPayload()));
        $response = $this->reader->readResponse($this->connection);
        if ($response !== 'OK') {
            throw new Exception\ProtocolException("Error publishing; server replied \"$response\"");
        }
    }
    
    /**
     * Subscribe to topic/channel
     *
     * @param string $topic A valid topic name: [.a-zA-Z0-9_-] and 1 < length < 32
     * @param string $channel Our channel name: [.a-zA-Z0-9_-] and 1 < length < 32
     *      "In practice, a channel maps to a downstream service consuming a topic."
     * @param callable $callback A callback that will be executed with a single
     *      parameter of the message object dequeued. Simply return TRUE to 
     *      mark the message as finished or throw an exception to cause a
     *      backed-off requeue
     * 
     * @throws Exception\ProtocolException If we receive unexpected response frame
     */
    public function subscribe($topic, $channel, $callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException(
                    '"callback" invalid; expecting a PHP callable'
                    );
        }
        
        $this->connection->write($this->writer->subscribe($topic, $channel, $this->shortId, $this->longId));
        $this->connection->write($this->writer->ready(1));
        
        while (1) {
            if ($this->connection->isReadable()) {
                try {
                    $msg = $this->reader->readMessage($this->connection);
                    call_user_func($callback, $msg);
                } catch (Exception\ResponseFrameException $e) {
                    // heartbeat?
                    if ($e->getMessage() === self::HEARTBEAT) {
                        if ($this->logger) {
                            $this->logger->debug('HEARTBEAT');
                        }
                        $this->connection->write($this->writer->nop());
                        continue;
                    } else {
                        throw new Exception\ProtocolException("Unexpected response frame: " . $e->getMessage(), NULL, $e);
                    }
                    continue;
                } catch (Exception\ReadException $e) {
                    // unable to read; give up
                    throw $e;
                } catch (\Exception $e) {
                    // requeue message according to backoff strategy; continue
                    
                    // @todo
                    
                    continue;
                }

                // mark as done; get next on the way
                $this->connection->write($this->writer->finish($msg->getId()));
                $this->connection->write($this->writer->ready(1));
            }
        }
    }
}