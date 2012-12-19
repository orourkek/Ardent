<?php

use Ardent\Push\Memory,
    Ardent\Push\Filterable;

class FilterTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @expectedException Ardent\FunctionException
     */
    public function testFilterThrowsExceptionOnInvalidCallback() {
        $stream = new Memory();
        $stream->filter('42 is definitely not callable', Filterable::FILTER_OUT);
    }
    
    public function testFilter() {
        $stream = new Memory();
        $stream->filter('strtoupper', Filterable::FILTER_OUT);
        
        $content = 'The cake is a lie';
        $stream->add($content);
        $stream->rewind();
        
        $pipe = new Memory();
        $stream->pipe($pipe);
        
        $stream->loop();
        $pipe->rewind();
        
        $this->assertEquals(strtoupper($content), $pipe->__toString());
    }
}
