<?php

use Ardent\Push\Memory;

class MemoryTest extends PHPUnit_Framework_TestCase {
    
    public function testCountReturnsNumberOfBytesInStreamRegardlessOfPosition() {
        $stream = new Memory();
        
        $content = 'The cake is a lie';
        $length = strlen($content);
        $stream->add($content);
        
        $this->assertEquals($length, $stream->count());
        $stream->rewind();
        $this->assertEquals($length, $stream->count());
    }
    
    public function testCountReturnsZeroOnKeyError() {
        $stream = $this->getMock('Ardent\\Push\\Memory', array('key'));
        $stream->add('Cake or death?!?!?!11');
        $stream->expects($this->at(0))
               ->method('key')
               ->will($this->returnValue(NULL));
        
        $this->assertEquals(0, $stream->count());
        
        $stream->expects($this->at(0))
               ->method('key')
               ->will($this->returnValue(42));
        
        $stream->expects($this->at(1))
               ->method('key')
               ->will($this->returnValue(NULL));
        
        $this->assertEquals(0, $stream->count());
    }
    
    public function testCountReturnsZeroOnSeekError() {
        $stream = $this->getMock('Ardent\\Push\\Memory', array('seek'));
        $stream->add('Cake or death?!?!?!11');
        $stream->expects($this->at(0))
               ->method('seek')
               ->will($this->returnValue(FALSE));
        
        $this->assertEquals(0, $stream->count());
        
        $stream->expects($this->at(0))
               ->method('seek')
               ->will($this->returnValue(TRUE));
        
        $stream->expects($this->at(1))
               ->method('seek')
               ->will($this->returnValue(FALSE));
        $this->assertEquals(0, $stream->count());
    }
    
    public function testValidReturnsTrueIfMoreBytesExistAfterTheCurrentPosition() {
        $stream = new Memory();
        $content = 'The cake is a lie';
        $stream->add($content);
        
        $this->assertTrue($stream->valid());
    }
    
    public function testValidReturnsFalseIfNoMoreDataExistAfterTheCurrentPosition() {
        $stream = new Memory();
        $content = 'The cake is a lie';
        $stream->add($content);
        $stream->loop();
        
        $this->assertFalse($stream->valid());
    }
    
    public function testCurrentReturnsCachedValueOnSuccessiveInvocations() {
        $stream = new Memory();
        $stream->setGranularity(1);
        
        $content = 'Test';
        $stream->add($content);
        $stream->rewind();
        
        $this->assertEquals(0, $stream->key());
        $this->assertEquals('T', $stream->current());
        $this->assertEquals(0, $stream->key());
        $this->assertEquals('T', $stream->current());
    }
    
    public function testToStringReturnsRemainingStreamContents() {
        $stream = new Memory();
        $stream->setGranularity(1);
        
        $content = 'Test';
        $stream->add($content);
        $stream->seek(2);
        
        $this->assertEquals('st', $stream->__toString());
    }
}