<?php
/**
 * Test pub
 * 
 * Pubs N message to "mytopic" topic, where N defaults to 10 but can be
 * supplied as argv1. Connects to nsqd on localhost, or argv2 (which can
 * be a , separated list of hosts)
 * 
 * php test-pub.php 100 nsq1,nsq2
 */

include __DIR__ . '/../bootstrap.php';

$n = isset($argv[1]) ? (int)$argv[1] : 10;
$hosts = isset($argv[2]) ? explode(',',$argv[2]) : array('localhost');

$runId = md5(microtime(TRUE));

// pub to all hosts; for now do that here (might move into client in future)
$nsqs = array();
foreach ($hosts as $h) {
    $conn = new nsqphp\Connection\Connection($h);
    $nsqs[$h] = new nsqphp\nsqphp($conn);
}

for ($i=1; $i<=$n; $i++) {
    foreach ($nsqs as $nsq) {
        $nsq->publish('mytopic', new nsqphp\Message\Message(json_encode(array('msg' => $i, 'run' => $runId))));
        echo "Published $i to `mytopic`\n";
    }
}
