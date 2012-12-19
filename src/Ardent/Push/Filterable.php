<?php

namespace Ardent\Push;

interface Filterable {
    
    const FILTER_IN = 1;
    const FILTER_OUT = 2;
    const FILTER_ALL = 3;
    
    /**
     * Attach a filter callback
     * 
     * @param callable $callback
     * @param int $mode One of [FILTER_IN|FILTER_OUT|FILTER_ALL]
     * @return void
     */
    function filter($callback, $mode);
}