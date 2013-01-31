# NSQPHP

PHP client for [NSQ](https://github.com/bitly/nsq).

### NSQ basics

You can read all about NSQ via the [readme on Github](https://github.com/bitly/nsq),
or via the [Bitly blog post](http://word.bitly.com/post/33232969144/nsq)
describing it. More details on nsqd, nsqlookupd are provided within
each folder within the project.

Here's some thing I have learned:

  - Clustering is provided only in the sense that nsqlookupd will discover
    which machines hosts messages for a particular topic. To consume from a
    cluster, you simply ask an instance of `nslookupd` where to find messages
    and then connect to every `nsqd` it tells you to (this is one of the things
    that makes nsq good).
  - HA (for pub) is easy due to the fact that each `nsqd` instance is isolated;
    you can simply connect to _any_ and send publish your message (I have built
    this into the client).
  - Resilience is provided by simply writing to more than one `nsqd` and then
    de-duplicating on subscribe (I have built this into the client).
  - nsq is not designed as a _work queue_ (for long running tasks) out of the
    box. The default setting of `msg-timeout` is 60,000ms (60 seconds). This is
    the time before nsq will automatically consider a message to have failed
    and hence requeue it. Our "work" should take much less time than this.
    Additionally, PHP is a blocking language, and although we are using a
    non-blocking IO event loop, any work you do to process a message will
    block the client from being able to reply to any heartbeats etc.


### Installation

I haven't packaged yet (eg: using composer). Easiest way is simply to clone:

    git clone git://github.com/davegardnerisme/nsqphp.git
    cd nsqphp
    git submodule update --init --recursive

To use `nsqphp` in your projects, just include the `bootstrap.php` file, or
setup autoloading. The design lends itself to a dependency injection
container (all dependencies are constructor injected), although you can just
setup the dependencies manually when you use it.

### Testing it out

Follow the [getting started guide](https://github.com/bitly/nsq#getting-started)
to install nsq on localhost.

Publish some events:

    php cruft/test-pub.php 10

Fire up a subscriber in one shell:

    php cruft/test-sub.php mychannel > /tmp/processed-messages

Then tail the redirected STDOUT in another shell, so you can see the messages
received and processed:

    tail -f /tmp/processed-messages

#### Note

In these tests I'm publishing _first_ since I haven't yet got the client to
automatically rediscover which nodes have messages for a given topic; hence
if you sub first, there are no nodes found with messages for the topic.


### Other tests

#### Multiple channels

The blog post describes a channel:

  | Each channel receives a copy of all the messages for a topic. In
  | practice, a channel maps to a downstream service consuming a topic.

So each message in a `topic` will be delivered to each `channel`.

Fire up two subscribers with different channels (one in each shell):

    php cruft/test-sub.php mychannel
    php cruft/test-sub.php otherchannel

Publish some messages:

    php cruft/test-pub.php 10

Each message will be delivered to each channel. It's also worth noting that
the API allows you to subscribe to multiple topics/channels within the same
process.


#### Multiple nsqds

Setup a bunch of servers running `nsqd` and `nsqlookupd` with hostnames
`nsq1`, `nsq2` ... Now publish a bunch of messages to both:

    php cruft/test-pub.php 10 nsq1
    php cruft/test-pub.php 10 nsq2

Now subscribe:

    php cruft/test-sub.php mychannel > /tmp/processed-messages

You will receive 20 messages.


#### Resilient delivery

Same test as before, but this time we deliver the _same message_ to two `nsqd`
instances and then de-duplicate on subscribe.

    php cruft/test-pub.php 10 nsq1,nsq2
    php cruft/test-sub.php mychannel > /tmp/processed-messages

This time you should receive **only 10 messages**.


### To do

  - Requeue failed messages using a back-off strategy (currently only simple
    fixed-delay requeue strategy)
  - Continuously re-evaluate which nodes contain messages for a given topic
    (that is subscribed to) and establish new connections for those clients
    (via event loop timer)


## The PHP client interface

### Messages

Messages are encapsulated by the nsqphp\Message\Message class and are referred
to by interface within the code (so you could implement your own).

Interface:

    public function getPayload();
    public function getId();
    public function getAttempts();
    public function getTimestamp();

### Publishing

The client supports publishing to N `nsqd` servers, which must be specified
explicitly by hostname. Unlike with subscription, there is no facility to 
lookup the hostnames via `nslookupd` (and we probably wouldn't want to anyway
for speed).

Minimal approach:

    $nsq = new nsqphp\nsqphp;
    $nsq->publishTo('localhost')
        ->publish('mytopic', new nsqphp\Message\Message('some message payload'));

It's up to you to decide if/how to encode your payload (eg: JSON).

HA publishing:

    $nsq = new nsqphp\nsqphp;
    $nsq->publishTo(array('nsq1', 'nsq2', 'nsq3'), nsqphp\nsqphp::PUB_QUORUM)
        ->publish('mytopic', new nsqphp\Message\Message('some message payload'));

We will require a quorum of the `publishTo` nsqd daemons to respond to consider
this operation a success (currently that happens in series). This is assuming
I have 3 `nsqd`s running on three hosts which are contactable via `nsq1` etc.

This technique is going to log messages twice, which will require
de-duplication on subscribe.

### Subscribing

The client supports subscribing from N `nsqd` servers, each of which will be
auto-discovered from one or more `nslookupd` servers. The way this works is
that `nslookupd` is able to provide a list of auto-discovered nodes hosting
messages for a given topic. This feature decouples our clients from having
to know where to find messages.

So when subscribing, the first thing we need to do is initialise our
lookup service object:

    $lookup = new nsqphp\Connection\Lookup;

Or alternatively:

    $lookup = new nsqphp\Connection\Lookup('nsq1,nsq2');

We can then use this to subscribe:

    $lookup = new nsqphp\Connection\Lookup;
    $nsq = new nsqphp\nsqphp($lookup);
    $nsq->subscribe('mytopic', 'somechannel', function($msg) {
        echo $msg->getId() . "\n";
        })->run();

**Warning: if our callback were to throw any Exceptions, the messages would
not be retried using these settings - read on to find out more.**

Or a bit more in the style of PHP (?):

    $lookup = new nsqphp\Connection\Lookup;
    $nsq = new nsqphp\nsqphp($lookup);
    $nsq->subscribe('mytopic', 'somechannel', 'msgCallback')
        ->run();

    function msgCallback($msg)
    {
        echo $msg->getId() . "\n";
    }

We can also subscribe to more than one channel/stream:

    $lookup = new nsqphp\Connection\Lookup;
    $nsq = new nsqphp\nsqphp($lookup);
    $nsq->subscribe('mytopic', 'somechannel', 'msgCallback')
        ->subscribe('othertopic', 'somechannel', 'msgCallback')
        ->run();

### Retrying failed messages

The PHP client will catch any thrown Exceptions that happen within the
callback and then either (a) retry, or (b) discard the messages. Usually you
won't want to discard the messages.

To fix this, we need a **requeue strategy** - this is in the form of any
object that implements `nsqphp\RequeueStrategy\RequeueStrategyInterface`:

    public function shouldRequeue(MessageInterface $msg);

The client currently ships with one; a fixed delay strategy:

    $requeueStrategy = new nsqphp\RequeueStrategy\FixedDelay;
    $lookup = new nsqphp\Connection\Lookup;
    $nsq = new nsqphp\nsqphp($lookup, NULL, $requeueStrategy);
    $nsq->subscribe('mytopic', 'somechannel', 'msgCallback')
        ->run();

    function msgCallback($msg)
    {
        if (rand(1,3) == 1) {
            throw new \Exception('Argh, something bad happened');
        }
        echo $msg->getId() . "\n";
    }

### De-duplication on subscribe

Recall that to achieve HA we simply duplicate on publish into
two different `nsqd` servers. To perform de-duplication we simply need to 
supply an object that implements `nsqphp\Dedupe\DedupeInterface`. 

    public function containsAndAdd($topic, $channel, MessageInterface $msg);

The PHP client ships with two mechanisms for de-duplicating messages on subscribe.
Both are based around [the opposite of a bloom filter](http://www.davegardner.me.uk/blog/2012/11/06/stream-de-duplication/).
One maintains a hash map as a PHP array (and hence bound to a single
process); the other calls out to Memcached and hence can share the data
structure between many processes.

We can use this thus:

    $requeueStrategy = new nsqphp\RequeueStrategy\FixedDelay;
    $dedupe = new nsqphp\Dedupe\OppositeOfBloomFilterMemcached;
    $lookup = new nsqphp\Connection\Lookup;
    $nsq = new nsqphp\nsqphp($lookup, $dedupe, $requeueStrategy);
    $nsq->subscribe('mytopic', 'somechannel', 'msgCallback')
        ->run();

    function msgCallback($msg)
    {
        if (rand(1,3) == 1) {
            throw new \Exception('Argh, something bad happened');
        }
        echo $msg->getId() . "\n";
    }

You can [read more about de-duplication on my blog](http://www.davegardner.me.uk/blog/2012/11/06/stream-de-duplication/),
however it's worth keeping the following in mind:

  - With Memcached de-duplication we can then happily launch N processes to
    subscribe to the same topic and channel, and only process the messages once.
  - De-duplication is not guaranteed (in fact far from it) - the implementations
    shipped are based on a lossy hash map, and hence are probabilistic in how
    they will perform. For events fed down at a similar time, they will usually
    perform acceptably (and they can be tuned to trade off memory usage for
    de-duplication abilities)
  - nsq is designed around the idea of idempotent subscribers - eg: your
    subscriber **must** be able to cope with processing a duplicated message
    (writing into Cassandra is an example of a system that copes well with
    executing something twice).


### Logging

The final optional dependency is a logger, in the form of some object that
implements `nsqphp\Logger\LoggerInterface` (there is no standard logger
interface shipped with PHP to the best of my knowledge):

    public function error($msg);
    public function warn($msg);
    public function info($msg);
    public function debug($msg);

The PHP client ships with a logger that dumps all logging information to STDERR.
Putting all of this together we'd have something similar to the `test-sub.php`
file:

    $requeueStrategy = new nsqphp\RequeueStrategy\FixedDelay;
    $dedupe = new nsqphp\Dedupe\OppositeOfBloomFilterMemcached;
    $lookup = new nsqphp\Connection\Lookup;
    $logger = new nsqphp\Logger\Stderr;
    $nsq = new nsqphp\nsqphp($lookup, $dedupe, $requeueStrategy, logger);
    $nsq->subscribe('mytopic', 'somechannel', 'msgCallback')
        ->run();

    function msgCallback($msg)
    {
        if (rand(1,3) == 1) {
            throw new \Exception('Argh, something bad happened');
        }
        echo $msg->getId() . "\n";
    }


## Design log

  - main client based on event loop (powered by React PHP) to allow us to
    handle multiple connections to multiple `nsqd` instances

