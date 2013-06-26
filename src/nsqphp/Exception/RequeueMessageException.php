<?php

namespace nsqphp\Exception;

class RequeueMessageException extends \RuntimeException
{
    private $delay;

    public function __construct($delay = 0, $message = '', $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->delay = (int) $delay;
    }

    public function getDelay()
    {
        return $this->delay;
    }
}
