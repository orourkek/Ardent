<?php

namespace Ardent\Push;

class Buffer {

    private $buffer;
    private $minBroadcastSize;
    private $filters = array();
    
    public function __construct($size) {
        $this->minBroadcastSize = $size;
    }
    
    public function __invoke($data) {
        $this->buffer .= $data;
        
        if (strlen($this->buffer) >= $this->minBroadcastSize) {
            $return = $this->applyFilters($this->buffer);
            $this->buffer = NULL;
        } else {
            $return = NULL;
        }
        
        return $return;
    }
    
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
    
    public function flush() {
        if (NULL === $this->buffer) {
            return $this->buffer;
        } else {
            $return = $this->applyFilters($this->buffer);
            $this->buffer = NULL;
            
            return $return;
        }
    }
}