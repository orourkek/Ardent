<?php

use Ardent\Push\String,
    Ardent\Push\Memory,
    Ardent\Push\Observables;

class SubjectTest extends PHPUnit_Framework_TestCase {
    
    public function testUnsubscribeAllClearsListeners() {
        $stream = new String('When I was your age, Pluto was still a planet.');
        
        $sink = new Memory;
        $stream->pipe($sink);
        
        $stream->current();
        $stream->next();
        
        $this->assertEquals(1, count($sink));
        $stream->unsubscribeAll();
        
        // Now that it has been unsubscribed, the $sink will no longer receive new data when the
        // stream is iterated over
        $stream->loop();
        
        $this->assertEquals(1, count($sink));
    }
}
