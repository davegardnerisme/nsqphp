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
    and then connect to every `nsqd` it tells you to.
  - HA (for pub) is easy due to the fact that each `nsqd` instance is isolated;
    you can simply connect to _any_ and send publish your message (so far I
    haven't got a publish interface that achieves this for you, you would
    have to write code to do it like the `test-pub.php` example.
  - Resilience is provided by simply writing to more than one `nsqd` and then
    de-duplicating on subscribe (I have built this into the client).

### Installation

I haven't packaged yet (eg: using composer). Easiest way is simply to clone:

    git clone git://github.com/davegardnerisme/nsqphp.git
    cd nsqphp
    git submodule --init --recursive update

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


### The PHP client interface





### Design log



### To do

  - Requeue failed messages using a back-off strategy (currently only simple
    fixed-delay requeue strategy)
  - Continuously re-evaluate which nodes contain messages for a given topic
    (that is subscribed to) and establish new connections for those clients
    (via event loop timer)
