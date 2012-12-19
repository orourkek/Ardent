<?php

namespace Ardent\Push;

/**
 * A write-only sink sending data to PHP's native STDOUT output stream
 */
class StdOut extends Subject implements Sink {
    
    /**
     * A hack so we can actually test this code in phpunit with reflected public visibility
     */
    private $stream = STDOUT;
    
    public function __invoke($data) {
        return $this->add($data);
    }
    
    public function add($data) {
        $bytesWritten = @fwrite($this->stream, $data);
        
        if (FALSE === $bytesWritten) {
            $errorInfo = error_get_last();
            $this->notify(Observable::ERROR, new StreamException(
                'STDOUT write failure: ' . $errorInfo['message']
            ));
            
            return 0;
        }
        
        return $bytesWritten;
    }
}