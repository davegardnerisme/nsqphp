<?php

include __DIR__ . '/../bootstrap.php';

$nsq = new nsqphp\nsqphpio();

$nsq->subscribe('foo', 'bar', function() {});