<?php

include __DIR__ . '/../bootstrap.php';

$logger = new nsqphp\Logger\Stderr;
$conn = new nsqphp\Connection\Connection;
$nsq = new nsqphp\nsqphp($conn, $logger);

$channel = isset($argv[1]) ? $argv[1] : 'foo';

$nsq->subscribe('mytopic', $channel, 'mycallback');

function mycallback($msg)
{
    echo "PROCESS\t" . $msg->getId() . "\t" . $msg->getPayload() . "\n";
}