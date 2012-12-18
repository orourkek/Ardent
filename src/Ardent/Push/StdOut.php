<?php

namespace Ardent\Push;

/**
 * A write-only sink directing data to PHP's output stream
 */
class StdOut extends Filter implements Sink {
    
    function add($data) {
        $data = $this->applyFilters($data);
        $bytesWritten = @fwrite(STDOUT, $data);
        
        if (FALSE === $bytesWritten) {
            $errorInfo = error_get_last();
            $this->notify(Events::ERROR, new StreamException(
                'STDOUT write failure: ' . $errorInfo['message']
            ));
        }
        
        return $bytesWritten;
    }
}