<?php

namespace Ardent\Streams;

/**
 * A write-only sink directing data to PHP's error output stream
 */
class StdErr extends Filter implements Sink {
    
    function add($data) {
        $data = $this->applyFilters($data);
        $bytes = @fwrite(STDERR, $data);
        
        if (FALSE === $bytes) {
            $errorInfo = error_get_last();
            $this->notify(Events::ERROR, new StreamException(
                'STDERR write failure: ' . $errorInfo['message']
            ));
        }
        
        return $bytes;
    }
}