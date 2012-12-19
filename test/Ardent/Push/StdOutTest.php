<?php

use Ardent\Push\StdOut,
    Ardent\Push\Memory,
    Ardent\Push\Observable;

class StdOutTest extends PHPUnit_Framework_TestCase {
    
    public function testAddWritesToStream() {
        $data = 'the cake is a lie';
        
        $stream = new Memory;
        $stream->add($data);
        $stream->rewind();
        
        $stdOut = new StdOut;
        $rawMemoryStream = fopen('php://memory', 'r+');
        $streamProp = new ReflectionProperty($stdOut, 'stream');
        $streamProp->setAccessible(true);
        $streamProp->setValue($stdOut, $rawMemoryStream);
        
        $stream->subscribe(array(Observable::DATA => $stdOut));
        
        $stream->loop();
        
        rewind($rawMemoryStream);
        $this->assertEquals($data, stream_get_contents($rawMemoryStream));
    }
    
    public function testMagicInvokeDelegatesToAdd() {
        $stdOut = $this->getMock('Ardent\\Push\\StdOut', array('add'));
        
        $stdOut->expects($this->once())
               ->method('add')
               ->with(42);
        
        $stdOut(42);
    }
    
    public function testAddBroadcastsErrorOnWriteFailure() {
        $stdOut = new StdOut;
        $badStream = new StdClass;
        $streamProp = new ReflectionProperty($stdOut, 'stream');
        $streamProp->setAccessible(true);
        $streamProp->setValue($stdOut, $badStream);
        
        $test = FALSE;
        $stdOut->subscribe(array(Observable::ERROR => function() use (&$test) {
            $test = TRUE;
        }));
        
        $this->assertEquals(0, $stdOut->add('test'));
        $this->assertTrue($test);
    }
}
