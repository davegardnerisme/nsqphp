<?php

namespace nsqphp\Wire;

class Writer
{
    /**
     * "Magic" identifier - for version we support
     */
    const MAGIC_V2 = "  V2";
    
    /**
     * Magic hello
     */
    public function magic()
    {
        return self::MAGIC_V2;
    }
    
    /**
     * Subscribe
     */
    public function subscribe($topic, $channel, $shortId, $longId)
    {
        return $this->command('SUB', $topic, $channel, $shortId, $longId);
    }
    
    /**
     * Publish
     */
    public function publish($topic, $message)
    {
        $cmd = $this->command('PUB', $topic);
        $data = $this->packString($message);
        $size = pack('N', strlen($data));
        return $cmd . $size . $data;
    }
    
    /**
     * Ready
     */
    public function ready($count)
    {
        return $this->command('RDY', $count);
    }
    
    public function finish($id)
    {
        return $this->command('FIN', $id);
    }

    public function requeue($id, $timeMs)
    {
        return $this->command('REQ', $id, $timeMs);
    }
    
    public function nop()
    {
        return $this->command('NOP');
    }
    
    public function close()
    {
        return $this->command('CLS');
    }
        
    /**
     * Command
     * 
     * @return string
     */
    private function command()
    {
        $args = func_get_args();
        $cmd = array_shift($args);
        return sprintf("%s %s%s", $cmd, implode(' ', $args), "\n");
    }
    
    private function packString($str)
    {        
        $outStr = "";
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $outStr .= pack("c", ord(substr($str, $i, 1))); 
        } 
        return $outStr; 
    } 
}