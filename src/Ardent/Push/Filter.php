<?php

namespace Ardent\Push;

abstract class Filter implements Filterable {
    
    private $inputFilters = array();
    private $outputFilters = array();
    
    final public function filter($callback, $mode) {
        if (!($mode == self::FILTER_IN || $mode == self::FILTER_OUT || $mode == self::FILTER_ALL)) {
            throw new \Ardent\DomainException(
                'Invalid filter mode'
            );
        } elseif (!is_callable($callback)) {
            throw new \Ardent\FunctionException(
                'Invalid filter callback'
            );
        }
        
        if ($mode == self::FILTER_IN || $mode == self::FILTER_ALL) {
            $this->inputFilters[] = $callback;
        }
        
        if ($mode == self::FILTER_OUT || $mode == self::FILTER_ALL) {
            $this->outputFilters[] = $callback;
        }
        
        return $this;
    }
    
    final protected function applyInputFilters($data) {
        foreach ($this->inputFilters as $transformation) {
            $data = $transformation($data);
        }
        
        return $data;
    }
    
    final protected function applyOutputFilters($data) {
        foreach ($this->outputFilters as $transformation) {
            $data = $transformation($data);
        }
        
        return $data;
    }
}