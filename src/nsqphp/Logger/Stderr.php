<?php

namespace nsqphp\Logger;

class Stderr implements LoggerInterface
{
    /**
     * Log error
     * 
     * @param string|\Exception $msg
     */
    public function error($msg)
    {
        $this->log('error', $msg);
    }
    
    /**
     * Log warn
     * 
     * @param string|\Exception $msg
     */
    public function warn($msg)
    {
        $this->log('warn', $msg);
    }
    
    /**
     * Log info
     * 
     * @param string|\Exception $msg
     */
    public function info($msg)
    {
        $this->log('info', $msg);
    }

    /**
     * Log debug
     * 
     * @param string|\Exception $msg
     */
    public function debug($msg)
    {
        $this->log('debug', $msg);
    }
    
    /**
     * Log
     * 
     * @param string $level
     * @param string|\Exception $msg
     */
    private function log($level, $msg)
    {
        $msg =  $msg instanceof \Exception ? $msg->getMessage() : (string)$msg;
        fwrite(STDERR, sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), strtoupper($level), $msg, PHP_EOL));
    }
}