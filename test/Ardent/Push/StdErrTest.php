<?php

use Ardent\Push\StdErr,
    Ardent\Push\Memory,
    Ardent\Push\Observable;

class StdErrTest extends PHPUnit_Framework_TestCase {
    
    public function testAddWritesToStream() {
        $data = 'the cake is a lie';
        
        $stream = new Memory;
        $stream->add($data);
        $stream->rewind();
        
        $stdErr = new StdErr;
        $rawMemoryStream = fopen('php://memory', 'r+');
        $streamProp = new ReflectionProperty($stdErr, 'stream');
        $streamProp->setAccessible(true);
        $streamProp->setValue($stdErr, $rawMemoryStream);
        
        $stream->subscribe(array(Observable::DATA => $stdErr));
        
        $stream->loop();
        
        rewind($rawMemoryStream);
        $this->assertEquals($data, stream_get_contents($rawMemoryStream));
    }
    
    public function testMagicInvokeDelegatesToAdd() {
        $stdErr = $this->getMock('Ardent\\Push\\StdErr', array('add'));
        
        $stdErr->expects($this->once())
               ->method('add')
               ->with(42);
        
        $stdErr(42);
    }
    
    public function testAddBroadcastsErrorOnWriteFailure() {
        $stdErr = new StdErr;
        $badStream = new StdClass;
        $streamProp = new ReflectionProperty($stdErr, 'stream');
        $streamProp->setAccessible(true);
        $streamProp->setValue($stdErr, $badStream);
        
        $test = FALSE;
        $stdErr->subscribe(array(Observable::ERROR => function() use (&$test) {
            $test = TRUE;
        }));
        
        $this->assertEquals(0, $stdErr->add('test'));
        $this->assertTrue($test);
    }
}
