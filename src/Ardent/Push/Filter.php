<?php

namespace Ardent\Push;

abstract class Filter {
    
    private $filters = array();
    
    final public function filter($callback) {
        if (is_callable($callback)) {
            $this->filters[] = $callback;
        } else {
            throw new \Ardent\FunctionException(
                'Invalid filter callback'
            );
        }
        
        return $this;
    }
    
    final protected function applyFilters($data) {
        foreach ($this->filters as $transformation) {
            $data = $transformation($data);
        }
        
        return $data;
    }
}