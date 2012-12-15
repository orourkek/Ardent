<?php

namespace Ardent\Streams;

/**
 * Streams are Iterators with the additional property of "observability"
 */
interface Streamable extends \Iterator, Observable {
    
    /**
     * Attach a filter to `Events::DATA` broadcasts
     * 
     * @param callable $callback
     * @return void
     */
    function filter($callback);
    
    /**
     * Attach a sink to which all future data events will be piped
     * 
     * This method is a shortcut for adding Sinks as subscribers to `Events::DATA` broadcasts.
     * The resulting Subscriber instance, like all event subscriptions, may be removed from the
     * observable Stream at any time.
     * 
     * @param Sink $sink
     * @return Subscriber
     */
    function pipe(Sink $sink);
    
    /**
     * Iterate over the Stream as long as it is valid
     */
    function loop();
}