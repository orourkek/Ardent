<?php

use Ardent\Push\File,
    Ardent\Push\Observable;

class FileTest extends PHPUnit_Framework_TestCase {
    
    public function testSeekBroadcastsNotificationOnError() {
        $stream = new File(new StdClass);
        $test = 0;
        $stream->subscribe(array(Observable::ERROR => function() use (&$test) { ++$test; }));
        
        $streamProp = new ReflectionProperty($stream, 'resource');
        $streamProp->setAccessible(true);
        $streamProp->setValue($stream, new StdClass); // Force an error on fseek
        
        $stream->seek(42);
        $this->assertEquals(1, $test);
    }
    
    public function testKeyBroadcastsNotificationOnStatError() {
        $stream = new File('php://memory');
        
        $streamProp = new ReflectionProperty($stream, 'resource');
        $streamProp->setAccessible(true);
        $streamProp->setValue($stream, new StdClass); // Force an error on fseek
        
        $test = FALSE;
        $stream->subscribe(array(Observable::ERROR => function() use (&$test) { $test = TRUE; }));
        
        $stream->key();
        $this->assertTrue($test);
    }
    
    public function testRewindBroadcastsNotificationOnStatError() {
        $stream = new File('php://memory');
        
        $streamProp = new ReflectionProperty($stream, 'resource');
        $streamProp->setAccessible(true);
        $streamProp->setValue($stream, new StdClass); // Force an error on fseek
        
        $test = FALSE;
        $stream->subscribe(array(Observable::ERROR => function() use (&$test) { $test = TRUE; }));
        
        $stream->rewind();
        $this->assertTrue($test);
    }
    
    public function testAddBroadcastsNotificationOnWriteError() {
        $stream = new File('php://memory');
        
        $streamProp = new ReflectionProperty($stream, 'resource');
        $streamProp->setAccessible(true);
        $streamProp->setValue($stream, new StdClass); // Force an error on fseek
        
        $test = FALSE;
        $stream->subscribe(array(Observable::ERROR => function() use (&$test) { $test = TRUE; }));
        
        $stream->add('test data');
        $this->assertTrue($test);
    }
    
    public function testInitializationBroadcastsNotificationOnOpenError() {
        $stream = new File(new StdClass);
        
        $test = FALSE;
        $stream->subscribe(array(Observable::ERROR => function() use (&$test) { $test = TRUE; }));
        
        $stream->current();
        $this->assertTrue($test);
    }
    
    public function testKeyInitializationBroadcastsNotificationOnOpenError() {
        $stream = new File(new StdClass);
        
        $test = FALSE;
        $stream->subscribe(array(Observable::ERROR => function() use (&$test) { $test = TRUE; }));
        
        $stream->key();
        $this->assertTrue($test);
    }
    
    public function testRewindInitializationBroadcastsNotificationOnOpenError() {
        $stream = new File(new StdClass);
        
        $test = FALSE;
        $stream->subscribe(array(Observable::ERROR => function() use (&$test) { $test = TRUE; }));
        
        $stream->rewind();
        $this->assertTrue($test);
    }
    
    public function testSeekInitializationBroadcastsNotificationOnOpenError() {
        $stream = new File(new StdClass);
        
        $test = FALSE;
        $stream->subscribe(array(Observable::ERROR => function() use (&$test) { $test = TRUE; }));
        
        $stream->seek(42);
        $this->assertTrue($test);
    }
}