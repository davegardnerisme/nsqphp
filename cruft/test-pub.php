<?php

include __DIR__ . '/../bootstrap.php';

$conn = new nsqphp\Connection\Connection;
$nsq = new nsqphp\nsqphp($conn);

$n = isset($argv[1]) ? (int)$argv[1] : 10;

for ($i=1; $i<=$n; $i++) {
    $nsq->publish('mytopic', new nsqphp\Message\Message(json_encode(array('msg' => $i, 'foo' => 'bar'))));
    echo "Published $i to `mytopic`\n";
}
