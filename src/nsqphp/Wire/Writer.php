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
     * 
     * @return string
     */
    public function magic()
    {
        return self::MAGIC_V2;
    }
    
    /**
     * Subscribe [SUB]
     * 
     * @param string $topic
     * @param string $channel
     * @param string $shortId
     * @param string $longId
     * 
     * @return string
     */
    public function subscribe($topic, $channel, $shortId, $longId)
    {
        return $this->command('SUB', $topic, $channel, $shortId, $longId);
    }
    
    /**
     * Publish [PUB]
     * 
     * @param string $topic
     * @param string $message
     * 
     * @return string
     */
    public function publish($topic, $message)
    {
        // the fast pack way, but may be unsafe
        $cmd = $this->command('PUB', $topic);
        $size = pack('N', strlen($message));
        return $cmd . $size . $message;
        
        // the safe way, but is time cost
        // $cmd = $this->command('PUB', $topic);
        // $data = $this->packString($message);
        // $size = pack('N', strlen($data));
        // return $cmd . $size . $data;
    }
    
    /**
     * Ready [RDY]
     * 
     * @param integer $count
     * 
     * @return string
     */
    public function ready($count)
    {
        return $this->command('RDY', $count);
    }
    
    /**
     * Finish [FIN]
     * 
     * @param string $id
     * 
     * @return string
     */
    public function finish($id)
    {
        return $this->command('FIN', $id);
    }

    /**
     * Requeue [REQ]
     *
     * @param string $id
     * @param integer $timeMs
     * 
     * @return string
     */
    public function requeue($id, $timeMs)
    {
        return $this->command('REQ', $id, $timeMs);
    }
    
    /**
     * No-op [NOP]
     *
     * @return string
     */
    public function nop()
    {
        return $this->command('NOP');
    }
    
    /**
     * Cleanly close [CLS]
     *
     * @return string
     */
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
    
    /**
     * Pack string -> binary
     *
     * @param string $str
     * 
     * @return string Binary packed
     */
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
