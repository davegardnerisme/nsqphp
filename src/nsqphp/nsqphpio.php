<?php

namespace nsqphp;

use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as ELFactory;

use nsqphp\Logger\LoggerInterface;
use nsqphp\Message\MessageInterface;
use nsqphp\Message\Message;

class nsqphpio
{
    /**
     * nsqlookupd service
     * 
     * @var Connection\Lookup
     */
    private $nsLookup;
    
    /**
     * Connection pool
     * 
     * @var Connection\ConnectionPool
     */
    private $connectionPool;
    
    /**
     * Event loop
     * 
     * @var LoopInterface
     */
    private $loop;
    
    
    /**
     * Constructor
     * 
     * @param Connection\Lookup $nsLookup Lookup service for hosts from topic
     */
    public function __construct(
            Connection\Lookup $nsLookup
            )
    {
        $this->nsLookup = $nsLookup;
        $this->connectionPool = new Connection\ConnectionPool;
        $this->loop = ELFactory::create();
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        // say goodbye
        /*
        $this->connection->write($this->writer->close());
        if ($this->logger) {
            $this->logger->info("nsqphp closing");
        }
*/
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
     * @throws Exception\NsLookupException If we cannot contact nslookupd to get hosts to connect to
     * @throws Exception\ProtocolException If we receive unexpected response/error frame
     */
    public function subscribe($topic, $channel, $callback)
    {
        $hosts = $this->nsLookup->lookupHosts($topic);
        $this->connectionPool->createAndAdd($hosts);
        
        foreach ($hosts as $host) {
            $conn = $this->connectionPool->find($host);
            $socket = $conn->getSocket();
            $this->loop->addReadStream($socket, function ($socket) use ($callback) {
                $connection = $this->connectionPool->find($socket);
                $frame = $this->reader->readFrame($connection);

                // intercept errors/responses
                if ($this->reader->frameIsHeartbeat($frame)) {
                    if ($this->logger) {
                        $this->logger->debug(sprintf('HEARTBEAT [%s]', (string)$connection));
                    }
                    $connection->write($this->writer->nop());
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
                            $connection->write($this->writer->requeue($msg->getId(), $delay));
                            $connection->write($this->writer->ready(1));
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
                $connection->write($this->writer->finish($msg->getId()));
                $connection->write($this->writer->ready(1));

            });
        }
    }

    /**
     * Run subscribe loop
     */
    public function run()
    {
        $this->loop->run();
    }
}
