<?php

use Ardent\Push\Memory;

class StreamSinkTest extends PHPUnit_Framework_TestCase {
    
    public function testPipe() {
        $stream = new Memory();
        
        $content = 'The cake is a lie';
        $stream->add($content);
        $stream->rewind();
        
        $pipe = new Memory();
        $stream->pipe($pipe);
        
        $stream->loop();
        $pipe->rewind();
        
        $this->assertEquals($content, $pipe->__toString());
    }
}
