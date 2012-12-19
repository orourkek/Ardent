<?php

namespace Ardent\Push;

abstract class Stream extends Subject implements Streamable, Sink {
    
    /**
     * Pipe all Stream data events to the specified sink
     * 
     * This method is essentially a shortcut for subscribing to data events on the stream.
     * Extraordinary errors that occur at a low-level while performing stream operations are
     * broadcast via the Observable::ERROR event.
     * 
     * @param Sink $sink
     * @return Subscription
     */
    public function pipe(Sink $sink) {
        $listeners = array(Observable::DATA => function($data) use ($sink) {
            $sink->add($data);
        });
        
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