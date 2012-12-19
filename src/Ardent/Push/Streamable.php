<?php

namespace Ardent\Push;

/**
 * Streams are Iterators with the additional property of "observability" and "filterability"
 */
interface Streamable extends \Iterator, Observable, Filterable {
    
    /**
     * Attach a sink to which all future data events will be piped
     * 
     * This method is a shortcut for adding Sinks as subscribers to `Observable::DATA` broadcasts.
     * The resulting Subscriber instance, like all event subscriptions, may be removed from the
     * observable Stream at any time.
     * 
     * @param Sink $sink
     * @return Subscriber
     */
    function pipe(Sink $sink);
    
    /**
     * Iterate over the Stream while it's valid
     */
    function loop();
}