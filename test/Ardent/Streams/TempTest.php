<?php

use Ardent\Streams\Temp;

class TempTest extends PHPUnit_Framework_TestCase {
    
    public function testTempStream() {
        $stream = new Temp();
        
        $content = 'The cake is a lie';
        $length = strlen($content);
        $stream->add($content);
        
        $this->assertEquals($length, $stream->count());
        $stream->rewind();
        $this->assertEquals($content, $stream->__toString());
    }
}
