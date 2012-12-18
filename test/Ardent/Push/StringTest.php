<?php

use Ardent\Push\String;

class StringTest extends PHPUnit_Framework_TestCase {
    
    public function testString() {
        $content = 'The cake is a lie';
        $stream = new String($content);
        
        $generated = '';
        while($stream->valid()) {
            $generated .= $stream->current();
            $stream->next();
        }
        
        $this->assertEquals($content, $generated);
    }
}
