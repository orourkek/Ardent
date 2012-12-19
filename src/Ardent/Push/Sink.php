<?php

namespace Ardent\Push;

interface Sink extends Filterable {
    
    /**
     * Dump new data into the sink
     */
    function add($data);
}