<?php
/**
 * Test subscription
 * 
 * Subscribes to "mytopic" topic using channel "foo" (or one provided as argv1).
 * Talks to nsqlookupd on localhost (by default), or one(s) provided as argv2.
 * 
 * php test-io.php bar nsq1,nsq2
 */

include __DIR__ . '/../bootstrap.php';

$hosts = isset($argv[2]) ? $argv[2] : 'localhost';

$logger = new nsqphp\Logger\Stderr;
$dedupe = new nsqphp\Dedupe\OppositeOfBloomFilterMemcached;
$lookup = new nsqphp\Connection\Lookup($hosts);
$requeueStrategy = new nsqphp\RequeueStrategy\FixedDelay;

$nsq = new nsqphp\nsqphpio($lookup, $dedupe, $requeueStrategy, $logger);

$channel = isset($argv[1]) ? $argv[1] : 'foo';

$nsq->subscribe('mytopic', $channel, function($msg) {
    echo "READ\t" . $msg->getId() . "\t" . $msg->getPayload() . "\n";
});

$nsq->run();

