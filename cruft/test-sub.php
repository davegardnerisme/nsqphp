<?php

include __DIR__ . '/../bootstrap.php';

$logger = new nsqphp\Logger\Stderr;
$conn = new nsqphp\Connection\Connection;
$nsq = new nsqphp\nsqphp($conn, $logger);

$nsq->subscribe('mytopic', 'foo', 'mycallback');

function mycallback($msg)
{
    echo "PROCESS\t" . $msg->getId() . "\t" . $msg->getPayload() . "\n";
}