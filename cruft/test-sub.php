<?php
/**
 * Test subscription
 * 
 * Subscribes to "mytopic" topic using channel "foo" (or one provided as argv1).
 */

include __DIR__ . '/../bootstrap.php';

$logger = new nsqphp\Logger\Stderr;
$requeueStrategy = new nsqphp\RequeueStrategy\FixedDelay;
$conn = new nsqphp\Connection\Connection;
$nsq = new nsqphp\nsqphp($conn, $requeueStrategy, $logger);

$channel = isset($argv[1]) ? $argv[1] : 'foo';

$nsq->subscribe('mytopic', $channel, 'mycallback');

function mycallback($msg)
{
    /*if (rand(1,3) == 1) {
        throw new \Exception('Random failure');
    }*/
    echo "PROCESS\t" . $msg->getId() . "\t" . $msg->getPayload() . "\n";
}