<?php

include __DIR__ . '/../bootstrap.php';

$n = isset($argv[1]) ? (int)$argv[1] : 10;
$hosts = isset($argv[2]) ? explode(',',$argv[2]) : array('localhost');

// pub to all hosts; for now do that here (might move into client in future)
$nsqs = array();
foreach ($hosts as $h) {
    $conn = new nsqphp\Connection\Connection($h);
    $nsqs[$h] = new nsqphp\nsqphp($conn);
}

for ($i=1; $i<=$n; $i++) {
    foreach ($nsqs as $nsq) {
        $nsq->publish('mytopic', new nsqphp\Message\Message(json_encode(array('msg' => $i, 'foo' => 'bar'))));
        echo "Published $i to `mytopic`\n";
    }
}
