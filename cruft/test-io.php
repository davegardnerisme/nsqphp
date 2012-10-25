<?php

include __DIR__ . '/../bootstrap.php';

$lookup = new nsqphp\Connection\Lookup;
$logger = new nsqphp\Logger\Stderr;
$nsq = new nsqphp\nsqphpio($lookup, $logger);

$nsq->subscribe('mytopic', 'bar', function($msg) {
    echo "READ " . $msg->getId() . "\n";
});

$nsq->run();

