<?php

namespace Ardent\Streams;

abstract class Stream extends Subject implements Streamable, Sink {
    
    /**
     * Pipe all Stream data events to the specified sink
     * 
     * This method is essentially a shortcut for subscribing to data events on the stream.
     * Extraordinary errors that occur at a low-level while performing stream operations are
     * broadcast via the Events::ERROR event. The optional `$throwOnError` parameter will cause
     * these errors to be thrown as exceptions if they occur. If set to FALSE, stream errors
     * encountered while reading from the source stream are silently ignored. Note that other
     * subscriptions to the same source stream may still specify their own handlers for errors
     * events.
     * 
     * @param Sink $sink
     * @param bool $throwOnError
     * @return Subscription
     */
    public function pipe(Sink $sink, $throwOnError = TRUE) {
        $listeners = array(Events::DATA => function($data) use ($sink) {
            $sink->add($data);
        });
        
        if (filter_var($throwOnError, FILTER_VALIDATE_BOOLEAN)) {
            $listeners[Events::ERROR] = function(StreamException $e) {
                throw $e;
            };
        }
        
        return $this->subscribe($listeners);
    }
    
    /**
     * Start a (possibly non-terminating) loop on the stream
     * 
     * @return void
     */
    public function loop() {
        while ($this->valid()) {
            $this->current();
            $this->next();
        }
    }
}