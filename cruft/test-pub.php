<?php
/**
 * Test pub
 * 
 * Pubs N message to "mytopic" topic, where N defaults to 10 but can be
 * supplied as argv1. Connects to nsqd on localhost, or argv2 (which can
 * be a , separated list of hosts). We can also specify how many replicas
 * must respond to consider operation a success.
 * 
 * php test-pub.php 100 nsq1,nsq2 1
 */

include __DIR__ . '/../bootstrap.php';

$n = isset($argv[1]) ? (int)$argv[1] : 10;
$hosts = isset($argv[2]) ? explode(',',$argv[2]) : array('localhost');
$replicas = isset($argv[3]) ? $argv[3] : 1;

$runId = md5(microtime(TRUE));

$nsq = new nsqphp\nsqphp;
$nsq->publishTo($hosts, $replicas);

for ($i=1; $i<=$n; $i++) {
    $nsq->publish('mytopic', new nsqphp\Message\Message(json_encode(array('msg' => $i, 'run' => $runId))));
    echo "Published $i to `mytopic`\n";
}
