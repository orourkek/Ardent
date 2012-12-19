<?php

namespace Ardent\Push;

class Buffer extends Filter {

    private $buffer;
    private $minBroadcastSize;
    
    public function __construct($size) {
        $this->minBroadcastSize = $size;
    }
    
    public function __invoke($data) {
        $data = $this->applyInputFilters($data);
        $this->buffer .= $data;
        
        if (strlen($this->buffer) >= $this->minBroadcastSize) {
            $return = $this->applyOutputFilters($this->buffer);
            $this->buffer = NULL;
        } else {
            $return = NULL;
        }
        
        return $return;
    }
    
    public function flush() {
        if (NULL === $this->buffer) {
            return $this->buffer;
        } else {
            $return = $this->applyOutputFilters($this->buffer);
            $this->buffer = NULL;
            
            return $return;
        }
    }
}