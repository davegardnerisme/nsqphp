<?php

include __DIR__ . '/../bootstrap.php';

$conn = new nsqphp\Connection\Connection;
$nsq = new nsqphp\nsqphp($conn);

for ($i=1; $i<=10; $i++) {
    $nsq->publish('mytopic', new nsqphp\Message\Message(json_encode(array('msg' => $i, 'foo' => 'bar'))));
    echo "Published $i to `mytopic`\n";
}
