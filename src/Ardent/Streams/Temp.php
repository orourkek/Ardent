<?php

namespace Ardent\Streams;

/**
 * A non-blocking, byte-based, in-memory stream with variable data granularity
 * 
 * The only difference between this stream and the Memory stream implementation is that a Temp
 * stream will transparently switch to filesystem storage of its data once the swap size memory
 * threshold is exceeded. This threshold defaults to 2 megabytes but may be customized at
 * instantiation time.
 */
class Temp extends File {
    
    public function __construct($swapSize = 2097152) {
        $swapSize = filter_var($swapSize, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 2097152,
                'min_range' => 1
            )
        ));
        
        parent::__construct("php://temp/maxmemory:$swapSize");
    }
}