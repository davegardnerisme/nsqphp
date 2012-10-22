# NSQPHP

PHP client for [NSQ](https://github.com/bitly/nsq). Currently in the early
stages with basic pub/sub ability and heartbeat handling.


### Usage

Currently you can grab the source, `git submodule --init --recursive update`
and then try out the examples:

    php cruft/test-sub.php

Then in another shell:

    php cruft/test-pub.php

The publish API is fairly straightforward:

    $conn = new nsqphp\Connection\Connection;
    $nsq = new nsqphp\nsqphp($conn);
    $nsq->publish('mytopic', new nsqphp\Message\Message('my payload)));

The subscription API currently works for a single topic (pending more
investigation into NSQ). You supply a callback which will get hit when a message
becomes available. The `subscribe()` method goes into a `while (1)` loop,
mainly to save you the bother of having to deal with heartbeats (the client
deals with these).

    $logger = new nsqphp\Logger\Stderr;
    $conn = new nsqphp\Connection\Connection;
    $nsq = new nsqphp\nsqphp($conn, $logger);

    $nsq->subscribe('mytopic', 'foo', 'mycallback');

    function mycallback($msg)
    {
        echo "PROCESS\t" . $msg->getId() . "\t" . $msg->getPayload() . "\n";
    }


### To do

  - Requeue failed messages using a back-off strategy (currently only simple
    fixed-delay requeue strategy)
  - Connect to `nsqlookupd` to find out where to connect to, figure out how to
    structure this within the client
