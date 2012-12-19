<?php

namespace Ardent\Push;

abstract class StreamSink extends Subject implements Streamable, Sink {
    
    private $filters = array();
    
    final public function filter($callback) {
        if (!is_callable($callback)) {
            throw new \Ardent\FunctionException(
                'Invalid filter callback'
            );
        }
        
        $this->filters[] = $callback;
        
        return $this;
    }
    
    final protected function applyFilters($data) {
        foreach ($this->filters as $transformation) {
            $data = $transformation($data);
        }
        
        return $data;
    }
    
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