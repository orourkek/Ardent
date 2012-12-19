<?php

use Ardent\Push\Subscription,
    Ardent\Push\Memory,
    Ardent\Push\Observable;

class SubscriptionTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @expectedException Ardent\EmptyException
     */
    public function testConstructThrowsExceptionOnEmptyCallbackArray() {
        $stream = new Subscription(new Memory, array());
    }
    
    /**
     * @expectedException Ardent\FunctionException
     */
    public function testConstructThrowsExceptionOnUncallableListener() {
        $stream = new Subscription(new Memory, array(
            Observable::DATA => function(){},
            Observable::DONE => '42 is not callable'
        ));
    }
}
