<?php

use Ardent\Push\Buffer, 
    Ardent\Push\Memory;

class BufferTest extends PHPUnit_Framework_TestCase {
    
    public function testBuffer() {
        $stream = new Memory();
        $buffer = new Buffer(3);
        $stream->filter($buffer);
        
        $stream->add('test');
        $stream->rewind();
        $stream->setGranularity(1);
        
        $result = '';
        for ($i=0; $i<3; $i++) {
            $result .= $stream->current();
            $stream->next();
        }
        
        $this->assertEquals('tes', $result);
        $this->assertNull($buffer->flush());
        
        $stream->current();
        $this->assertEquals('t', $buffer->flush());
    }
}
