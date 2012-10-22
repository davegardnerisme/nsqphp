<?php

namespace nsqphp;

use nsqphp\Connection\ConnectionInterface;
use nsqphp\Logger\LoggerInterface;
use nsqphp\RequeueStrategy\RequeueStrategyInterface;
use nsqphp\Message\MessageInterface;
use nsqphp\Message\Message;

class nsqphp
{
    /**
     * Connection
     * 
     * @var ConnectionInterface
     */
    private $connection;
    
    /**
     * Requeue strategy
     * 
     * @var RequeueStrategyInterface
     */
    private $requeueStrategy;
    
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
     * @param RequeueStrategyInterface|NULL $requeueStrategy
     * @param LoggerInterface|NULL $logger
     */
    public function __construct(
            ConnectionInterface $connection,
            RequeueStrategyInterface $requeueStrategy = NULL,
            LoggerInterface $logger = NULL
            )
    {
        $this->connection = $connection;
        $this->requeueStrategy = $requeueStrategy;
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
        $frame = $this->reader->readFrame($this->connection);
        if (!$this->reader->frameIsResponse($frame, 'OK')) {
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
     * @throws \InvalidArgumentException If we don't have a valid callback
     * @throws Exception\ProtocolException If we receive unexpected response/error frame
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
                $frame = $this->reader->readFrame($this->connection);

                // intercept errors/responses
                if ($this->reader->frameIsHeartbeat($frame)) {
                    if ($this->logger) {
                        $this->logger->debug('HEARTBEAT');
                    }
                    $this->connection->write($this->writer->nop());
                    continue;
                } elseif ($this->reader->frameIsMessage($frame)) {
                    $msg = Message::fromFrame($frame);
                    try {
                        call_user_func($callback, $msg);
                    } catch (\Exception $e) {
                        if ($this->logger) {
                            $this->logger->warn('Error processing "' . $msg->getId() . '": ' . $e->getMessage());
                        }
                        // requeue message according to backoff strategy; continue
                        if ($this->requeueStrategy !== NULL
                                && ($delay = $this->requeueStrategy->shouldRequeue($msg)) !== NULL) {
                            // requeue
                            if ($this->logger) {
                                $this->logger->debug('Requeuing "' . $msg->getId() . '" with delay "' . $delay . '"');
                            }
                            $this->connection->write($this->writer->requeue($msg->getId(), $delay));
                            $this->connection->write($this->writer->ready(1));
                            continue;
                        } else {
                            if ($this->logger) {
                                $this->logger->debug('Not requeing "' . $msg->getId() . '"');
                            }
                        }
                    }
                } else {
                    // @todo handle error responses a bit more cleverly
                    throw new Exception\ProtocolException("Error/unexpected frame received: " . json_encode($frame), NULL, $e);
                }

                // mark as done; get next on the way
                $this->connection->write($this->writer->finish($msg->getId()));
                $this->connection->write($this->writer->ready(1));
            }
        }
    }
}