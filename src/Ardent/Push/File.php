<?php

namespace Ardent\Push;

/**
 * A non-blocking, byte-based, filesystem stream with variable data granularity
 */
class File extends Stream implements \Ardent\CountableSeekableIterator {
    
    private $uri;
    private $mode = 'ab+';
    private $resource;
    private $granularity = 8192;
    
    private $currentCache = NULL;
    private $keyCache = NULL;
    
    public function __construct($uri) {
        $this->uri = $uri;
        $this->notify(Observable::READY);
    }
    
    public function __destruct() {
        $this->close();
    }
    
    /**
     * @link http://php.net/manual/en/countable.count.php
     * @return int Returns the total size in bytes of the stream or ZERO on failure
     */
    public function count() {
        $currentPos = $this->key();
        if (NULL === $currentPos) {
            return 0;
        }
        
        if (!$this->seek(0, SEEK_END)) {
            return 0;
        }
        
        $endPos = $this->key();
        if (NULL === $endPos) {
            return 0;
        }
        
        if (!$this->seek($currentPos)) {
            return 0;
        }
        
        return $endPos;
    }

    /**
     * Note that the return type differs from the normal SeekableIterator interface
     * @link http://php.net/manual/en/seekableiterator.seek.php
     * @param int $position
     * @param int $whence
     * @return bool Returns TRUE on success or FALSE on failure
     */
    public function seek($position, $whence = SEEK_SET) {
        if (0 === @fseek($this->resource, $position, $whence)) {
            return TRUE;
        } else {
            $errorInfo = error_get_last();
            $this->notify(Observable::ERROR, new StreamException(
                'Stream seek failure: ' . $errorInfo['message']
            ));
            
            return FALSE;
        }
    }

    /**
     * @link http://www.php.net/manual/en/iterator.rewind.php
     * @return void
     */
    public function rewind() {
        if (!@rewind($this->resource)) {
            $errorInfo = error_get_last();
            $e = new StreamException(
                "Stream stat failure: " . $errorInfo['message']
            );

            $this->notify(Observable::ERROR, $e);
        }
    }

    /**
     * @link http://www.php.net/manual/en/iterator.valid.php
     * @return bool
     */
    public function valid() {
        return !@feof($this->resource);
    }

    /**
     * @link http://php.net/manual/en/iterator.key.php
     * @return int Or NULL on failure
     */
    public function key() {
        if ($this->keyCache !== NULL) {
            return $this->keyCache;
        }

        $position = @ftell($this->resource);

        if (FALSE === $position) {
            $errorInfo = error_get_last();
            $this->notify(Observable::ERROR, new StreamException(
                "Stream stat failure: " . $errorInfo['message']
            ));

            return NULL;

        }

        return $this->keyCache = $position;
    }

    /**
     * @link http://php.net/manual/en/iterator.current.php
     * @return string Returns bytes up to the current granularity or NULL on failure or empty data
     */
    public function current() {
        if ($this->currentCache !== NULl) {
            return $this->currentCache;
        } elseif ($this->resource) {
            return $this->currentCache = $this->read();
        } elseif ($this->initialize()) {
            return $this->currentCache = $this->read();
        } else {
            return NULL;
        }
    }

    /**
     * @link http://www.php.net/manual/en/iterator.next.php
     * @return void
     */
    public function next() {
        $this->currentCache = NULL;
        $this->keyCache = NULL;
    }
    
    private function read() {
        $data = @fread($this->resource, $this->granularity);
        
        if (FALSE === $data) {
            $errorInfo = error_get_last();
            $this->notify(Observable::ERROR, new StreamException(
                "Stream read failure: " . $errorInfo['message']
            ));
            return NULL;
        } elseif ($data !== '') {
            $data = $this->applyOutputFilters($data);
            $this->notify(Observable::DATA, $data);
            return $data;
        } else {
            return NULL;
        }
    }

    /**
     * @param mixed $data
     * @return int
     */
    public function add($data) {
        if (!$this->resource) {
            $this->initialize();
        }
        
        $data = $this->applyInputFilters($data);
        
        $bytes = @fwrite($this->resource, $data);
        
        if (FALSE === $bytes) {
            $errorInfo = error_get_last();
            $this->notify(Observable::ERROR, new StreamException(
                'Stream write failure: ' . $errorInfo['message']
            ));
        }
        
        return $bytes;
    }
    
    protected function initialize() {
        if ($this->resource = @fopen($this->uri, $this->mode)) {
            stream_set_blocking($this->resource, 0);
            
            return TRUE;
        } else {
            $errorInfo = error_get_last();
            $this->notify(Observable::ERROR, new StreamException(
                'Stream initialization failure: ' . $errorInfo['message']
            ));
            
            return FALSE;
        }
    }

    /**
     * @return void
     */
    public function close() {
        if ($this->resource) {
            @fclose($this->resource);
        }
    }

    /**
     * @param int $bytes
     * @return File
     */
    public function setGranularity($bytes) {
        $this->granularity = filter_var($bytes, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 8192,
                'min_range' => 1
            )
        ));
        
        return $this;
    }
    
    /**
     * Will NOT broadcast events!
     * @return string
     */
    public function __toString() {
        return is_resource($this->resource) ? stream_get_contents($this->resource) : '';
    }
}