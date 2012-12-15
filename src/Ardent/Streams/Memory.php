<?php

namespace Ardent\Streams;

/**
 * A non-blocking, byte-based, in-memory stream with variable data granularity
 */
class Memory extends File {
    
    public function __construct() {
        parent::__construct('php://memory');
    }
}