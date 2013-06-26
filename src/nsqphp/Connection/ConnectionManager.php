<?php

namespace nsqphp\Connection;

class ConnectionManager extends ConnectionPool
{
    private function __construct()
    {
    }

    public static function getInstance()
    {
        static $instance;
        if (!$instance) {
            $instance = new self;
        }
        return $instance;
    }
}
