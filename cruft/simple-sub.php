<?php
/**
 * Test subscription
 * 
 * Subscribes to "mytopic" topic using channel "foo" (or one provided as argv1).
 * 
 * php test-sub.php bar
 */

include __DIR__ . '/../bootstrap.php';

$logger = new nsqphp\Logger\Stderr;
$dedupe = new nsqphp\Dedupe\OppositeOfBloomFilterMemcached;
$lookup = new nsqphp\Lookup\FixedHosts('localhost:4150');
$requeueStrategy = new nsqphp\RequeueStrategy\FixedDelay;

$nsq = new nsqphp\nsqphp($lookup, $dedupe, $requeueStrategy, $logger);

$channel = isset($argv[1]) ? $argv[1] : 'foo';

$nsq->subscribe('mytopic', $channel, function($msg) {
    echo "READ\t" . $msg->getId() . "\t" . $msg->getPayload() . "\n";
});

$nsq->run();

