<?php

namespace nsqphp\RequeueStrategy;

/**
 * Fixed delay requeue strategy
 *
 * Retry all failed messages N times with "start * (growFactor^N)" delay.
 */
class GrowingDelay extends DelaysList
{
    /**
     * Constructor
     *
     * @param int $maxAttempts
     * @param int $start
     * @param float $growFactor
     * @throws \InvalidArgumentException
     */
    public function __construct($maxAttempts = 10, $start = 50, $growFactor = 2)
    {
        $delays = array();
        $current = $start;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $delays[] = (int) $current;
            $current *= $growFactor;
        }
        parent::__construct($maxAttempts, $delays);
    }
}
