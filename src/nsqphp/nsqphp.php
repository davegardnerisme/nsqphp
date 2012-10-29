<?php

namespace nsqphp;

use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as ELFactory;

use nsqphp\Logger\LoggerInterface;
use nsqphp\Connection\Lookup;
use nsqphp\Connection\ConnectionInterface;
use nsqphp\Dedupe\DedupeInterface;
use nsqphp\RequeueStrategy\RequeueStrategyInterface;
use nsqphp\Message\MessageInterface;
use nsqphp\Message\Message;

class nsqphp
{
    /**
     * Publish "consistency levels" [ish]
     */
    const PUB_ONE = 1;
    const PUB_TWO = 2;
    const PUB_QUORUM = 5;
    
    /**
     * nsqlookupd service
     * 
     * @var Lookup|NULL
     */
    private $nsLookup;
    
    /**
     * Dedupe service
     * 
     * @var DedupeInterface|NULL
     */
    private $dedupe;
    
    /**
     * Requeue strategy
     * 
     * @var RequeueStrategyInterface|NULL
     */
    private $requeueStrategy;
    
    /**
     * Logger, if any enabled
     * 
     * @var LoggerInterface|NULL
     */
    private $logger;
    
    /**
     * Connection timeout - in seconds
     * 
     * @var float
     */
    private $connectionTimeout;
    
    /**
     * Read/write timeout - in seconds
     * 
     * @var float
     */
    private $readWriteTimeout;
    
    /**
     * Read wait timeout - in seconds
     * 
     * @var float
     */
    private $readWaitTimeout;

    /**
     * Connection pool for subscriptions
     * 
     * @var Connection\ConnectionPool
     */
    private $subConnectionPool;

    /**
     * Connection pool for publishing
     * 
     * @var Connection\ConnectionPool|NULL
     */
    private $pubConnectionPool;
    
    /**
     * Publish success criteria (how many nodes need to respond)
     * 
     * @var integer
     */
    private $pubSuccessCount;

    /**
     * Event loop
     * 
     * @var LoopInterface
     */
    private $loop;
    
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
     * Long ID (of who we are)
     * 
     * @var string
     */
    private $longId;
    
    /**
     * Short ID (of who we are)
     * 
     * @var string
     */
    private $shortId;
    
    /**
     * Constructor
     * 
     * @param Lookup|NULL $nsLookup Lookup service for hosts from topic (optional)
     *      NB: $nsLookup service _is_ required for subscription
     * @param DedupeInterface|NULL $dedupe Deduplication service (optional)
     * @param RequeueStrategyInterface|NULL $requeueStrategy Our strategy
     *      for dealing with failures whilst processing SUBbed messages via
     *      callback - if any (optional)
       @param LoggerInterface|NULL $logger Logging service (optional)
     */
    public function __construct(
            Lookup $nsLookup = NULL,
            DedupeInterface $dedupe = NULL,
            RequeueStrategyInterface $requeueStrategy = NULL,
            LoggerInterface $logger = NULL,
            $connectionTimeout = 3,
            $readWriteTimeout = 3,
            $readWaitTimeout = 15
            )
    {
        $this->nsLookup = $nsLookup;
        $this->dedupe = $dedupe;
        $this->requeueStrategy = $requeueStrategy;
        $this->logger = $logger;
        
        $this->connectionTimeout = $connectionTimeout;
        $this->readWriteTimeout = $readWriteTimeout;
        $this->readWaitTimeout = $readWaitTimeout;
        $this->pubSuccessCount = 1;
        
        $this->subConnectionPool = new Connection\ConnectionPool;
        
        $this->loop = ELFactory::create();
        
        $this->reader = new Wire\Reader;
        $this->writer = new Wire\Writer;

        $hn = exec('hostname -f');
        $parts = explode('.', $hn);
        $this->shortId = $parts[0];
        $this->longId = $hn;
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        // say goodbye to each connection
        foreach ($this->subConnectionPool as $connection) {
            $connection->write($this->writer->close());
            if ($this->logger) {
                $this->logger->info(sprintf('nsqphp closing [%s]', (string)$connection));
            }
        }
    }
    
    /**
     * Define nsqd hosts to publish to
     * 
     * We'll remember these hosts for any subsequent publish() call, so you
     * only need to call this once to publish 
     * 
     * @param string|array $hosts
     * @param integer|NULL $cl Consistency level - basically how many `nsqd`
     *      nodes we need to respond to consider a publish successful
     *      The default value is nsqphp::PUB_ONE
     * 
     * @throws \InvalidArgumentException If bad CL provided
     * @throws \InvalidArgumentException If we cannot achieve the desired CL
     *      (eg: if you ask for PUB_TWO but only supply one node)
     * 
     * @return nsqphp This instance for call chaining
     */
    public function publishTo($hosts, $cl = NULL)
    {
        $this->pubConnectionPool = new Connection\ConnectionPool;

        if (!is_array($hosts)) {
            $hosts = explode(',', $hosts);
        }
        foreach ($hosts as $h) {
            if (strpos($h, ':') === FALSE) {
                $h .= ':4150';
            }
            
            $parts = explode(':', $h);
            $conn = new Connection\Connection(
                    $parts[0],
                    isset($parts[1]) ? $parts[1] : NULL,
                    $this->connectionTimeout,
                    $this->readWriteTimeout,
                    $this->readWaitTimeout,
                    FALSE,      // blocking
                    array($this, 'connectionCallback')
                    );
            $this->pubConnectionPool->add($conn);
        }
        
        // work out success count
        if ($cl === NULL) {
            $cl = self::PUB_ONE;
        }
        switch ($cl) {
            case self::PUB_ONE:
            case self::PUB_TWO:
                $this->pubSuccessCount = $cl;
                break;
            case self::PUB_QUORUM:
                $this->pubSuccessCount = ceil($this->pubConnectionPool->count() / 2) + 1;
                break;
            default:
                throw new \InvalidArgumentException('Invalid consistency level');
                break;
        }
        if ($this->pubSuccessCount > $this->pubConnectionPool->count()) {
            throw new \InvalidArgumentException(sprintf('Cannot achieve desired consistency level with %s nodes', $this->pubConnectionPool->count()));
        }
    }
    
    /**
     * Publish message
     *
     * @param string $topic A valid topic name: [.a-zA-Z0-9_-] and 1 < length < 32
     * @param MessageInterface $msg
     * 
     * @throws Exception\PublishException If we don't get "OK" back from server
     *      (for the specified number of hosts - as directed by `publishTo`)
     * 
     * @return nsqphp This instance for call chaining
     */
    public function publish($topic, MessageInterface $msg)
    {
        // pick a random
        $this->pubConnectionPool->shuffle();
        
        $success = 0;
        $errors = array();
        foreach ($this->pubConnectionPool as $conn) {
            try {
                $conn->write($this->writer->publish($topic, $msg->getPayload()));
                $frame = $this->reader->readFrame($conn);
                if ($this->reader->frameIsResponse($frame, 'OK')) {
                    $success++;
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
            if ($success >= $this->pubSuccessCount) {
                break;
            }
        }
        
        if ($success < $this->pubSuccessCount) {
            throw new Exception\PublishException(
                    sprintf('Failed to publish message; required %s for success, achieved %s. Error were: %s', $this->pubSuccessCount, $success, implode(', ', $errors))
                    );
        }
        
        return $this;
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
     * @throws \RuntimeException If we don't have a valid callback
     * @throws \InvalidArgumentException If we don't have a valid callback
     * 
     * @return nsqphp This instance of call chaining
     */
    public function subscribe($topic, $channel, $callback)
    {
        if ($this->nsLookup === NULL) {
            throw new \RuntimeException(
                    'nsqphp initialised without providing lookup service (required for sub).'
                    );
        }
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException(
                    '"callback" invalid; expecting a PHP callable'
                    );
        }
        
        // we need to instantiate a new connection for every nsqd that we need
        // to fetch messages from for this topic/channel

        $hosts = $this->nsLookup->lookupHosts($topic);
        if ($this->logger) {
            $this->logger->debug("Found the following hosts for topic \"$topic\": " . implode(',', $hosts));
        }

        foreach ($hosts as $host) {
            $parts = explode(':', $host);
            $conn = new Connection\Connection(
                    $parts[0],
                    isset($parts[1]) ? $parts[1] : NULL,
                    $this->connectionTimeout,
                    $this->readWriteTimeout,
                    $this->readWaitTimeout,
                    TRUE    // non-blocking
                    );
            if ($this->logger) {
                $this->logger->info("Connecting to {$host} and saying hello");
            }
            $conn->write($this->writer->magic());
            $this->subConnectionPool->add($conn);
            $socket = $conn->getSocket();
            $nsq = $this;
            $this->loop->addReadStream($socket, function ($socket) use ($nsq, $callback, $topic, $channel) {
                $nsq->readAndDispatchMessage($socket, $topic, $channel, $callback);
            });
            
            // subscribe
            $conn->write($this->writer->subscribe($topic, $channel, $this->shortId, $this->longId));
            $conn->write($this->writer->ready(1));
        }
        
        return $this;
    }

    /**
     * Run subscribe event loop
     */
    public function run()
    {
        $this->loop->run();
    }
    
    /**
     * Read/dispatch callback for async sub loop
     * 
     * @param Resource $socket The socket that a message is available on
     * @param string $topic The topic subscribed to that yielded this message
     * @param string $channel The channel subscribed to that yielded this message
     * @param callable $callback The callback to execute to process this message
     */
    public function readAndDispatchMessage($socket, $topic, $channel, $callback)
    {
        $connection = $this->subConnectionPool->find($socket);
        $frame = $this->reader->readFrame($connection);

        if ($this->logger) {
            $this->logger->debug(sprintf('Read frame for topic=%s channel=%s [%s] %s', $topic, $channel, (string)$connection, json_encode($frame)));
        }

        // intercept errors/responses
        if ($this->reader->frameIsHeartbeat($frame)) {
            if ($this->logger) {
                $this->logger->debug(sprintf('HEARTBEAT [%s]', (string)$connection));
            }
            $connection->write($this->writer->nop());
        } elseif ($this->reader->frameIsMessage($frame)) {
            $msg = Message::fromFrame($frame);
            
            if ($this->dedupe !== NULL && $this->dedupe->containsAndAdd($topic, $channel, $msg)) {
                if ($this->logger) {
                    $this->logger->debug(sprintf('Deduplicating [%s] "%s"', (string)$connection, $msg->getId()));
                }
            } else {
                try {
                    call_user_func($callback, $msg);
                } catch (\Exception $e) {
                    if ($this->logger) {
                        $this->logger->warn(sprintf('Error processing [%s] "%s": %s', (string)$connection, $msg->getId(), $e->getMessage()));
                    }
                    // requeue message according to backoff strategy; continue
                    if ($this->requeueStrategy !== NULL
                            && ($delay = $this->requeueStrategy->shouldRequeue($msg)) !== NULL) {
                        // requeue
                        if ($this->logger) {
                            $this->logger->debug(sprintf('Requeuing [%s] "%s" with delay "%s"', (string)$connection, $msg->getId(), $delay));
                        }
                        $connection->write($this->writer->requeue($msg->getId(), $delay));
                        $connection->write($this->writer->ready(1));
                        continue;
                    } else {
                        if ($this->logger) {
                            $this->logger->debug(sprintf('Not requeuing [%s] "%s"', (string)$connection, $msg->getId()));
                        }
                    }
                }
            }
            
            // mark as done; get next on the way
            $connection->write($this->writer->finish($msg->getId()));
            $connection->write($this->writer->ready(1));

        } else {
            // @todo handle error responses a bit more cleverly
            throw new Exception\ProtocolException("Error/unexpected frame received: " . json_encode($frame), NULL, $e);
        }
    }
    
    /**
     * Connection callback
     * 
     * @param ConnectionInterface $connection
     */
    public function connectionCallback(ConnectionInterface $connection)
    {
        if ($this->logger) {
            $this->logger->info("Connecting to " . (string)$connection . " and saying hello");
        }
        $connection->write($this->writer->magic());
    }
}
