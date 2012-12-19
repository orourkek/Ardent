<?php

namespace Ardent\Push;

interface Sink {
    
    /**
     * Dump new data into the sink
     */
    function add($data);
}