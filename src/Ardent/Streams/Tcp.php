<?php

namespace Ardent\Streams;

class Tcp extends Stream {
    
    const CONN_NONE = 0;
    const CONN_PENDING = 100;
    const CONN_READY = 200;
    
    private $uri;
    private $persistent = FALSE;
    private $granularity = 8192;
    private $buffer;
    
    protected $socket;
    protected $state = self::CONN_NONE;
    
    public function __construct($uri, $persistent = FALSE) {
        if (!$uriParts = @parse_url($uri)) {
            throw new \Ardent\DomainException(
                'Invalid socket URI'
            );
        } elseif (empty($uriParts['scheme'])) {
            throw new \Ardent\DomainException(
                'Invalid socket URI scheme'
            );
        } elseif (!($uriParts['scheme'] == 'tcp' || $uriParts['scheme'] == 'udp')) {
            throw new \Ardent\DomainException(
                'Invalid socket URI scheme'
            );
        } elseif (empty($uriParts['port'])) {
            throw new \Ardent\DomainException(
                'Invalid socket URI port'
            );
        } elseif (empty($uri['path']) || $uri['path'] == '/') {
            $uri = rtrim($uri, '/') . '/' . spl_object_hash($this);
        }
        
        $this->uri = $uri;
        $this->persistent = filter_var($persistent, FILTER_VALIDATE_BOOLEAN);
    }
    
    public function __destruct() {
        if (!$this->persistent) {
            $this->close();
        }
    }
    
    public function __toString() {
        return $this->uri;
    }

    /**
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Current data or NULL on failure
     */
    public function current() {
        if (NULL !== $this->buffer) {
            return $this->buffer;
        }
        
        switch ($this->state) {
            case self::CONN_NONE:
                $this->connect();
                break;
            case self::CONN_PENDING:
                $read = $ex = array();
                $write = array($this->socket);
                if ($this->doSelect($read, $write, $ex, 0, 0)) {
                    $this->state = self::CONN_READY;
                    $this->notify(Events::READY);
                }
                break;
            case self::CONN_READY:
                $read = array($this->socket);
                $write = $ex = array();
                if ($this->doSelect($read, $write, $ex, 0, 0) && ($data = $this->read())) {
                    $this->notify(Events::DATA, $data);
                    return $data;
                }
                break;
        }
        
        return NULL;
    }
    
    protected function connect() {
        list($socket, $errNo, $errStr) = $this->makeSocketStream();
        
        if (FALSE !== $socket || $errNo === SOCKET_EWOULDBLOCK) {
            $this->state = self::CONN_PENDING;
            $this->socket = $socket;
            stream_set_blocking($socket, 0);
        } else {
            $msg = "Socket connection failure: {$this->uri}";
            $msg .= $errNo ? "; [Error# $errNo] $errStr" : '';
            
            $this->notify(Events::ERROR, new StreamException($msg));
        }
    }
    
    private function makeSocketStream() {
        $context = $this->buildContext();
        
        if ($this->persistent) {
            $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT;
        } else {
            $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        }
        
        $socket = @stream_socket_client(
            $this->uri,
            $errNo,
            $errStr,
            42, // <--- value not used with ASYNC connections
            $flags,
            $context
        );
        
        return array($socket, $errNo, $errStr);
    }
    
    protected function buildContext() {
        return stream_context_create(array());
    }
    
    protected function read() {
        $data = @fread($this->socket, $this->granularity);
        
        if (!(FALSE === $data || $data === '')) {
            $this->buffer = $data;
            return $data;
        } elseif (!is_resource($this->socket) || feof($this->socket)) {
            $this->notify(Events::ERROR, new StreamException(
                'Socket has gone away :('
            ));
        }
        
        return NULL;
    }

    /**
     * @link http://php.net/manual/en/iterator.key.php
     * @return int The current position or NULL on failure
     */
    public function key() {
        if (FALSE !== ($pos = @ftell($this->socket))) {
            return $pos;
        }
        
        $errorInfo = error_get_last();
        $e = new StreamException(
            'Socket stream failure: ' . $errorInfo['message']
        );
        
        $this->notify(Events::ERROR, $e);

        return NULL;
    }

    /**
     * @link http://php.net/manual/en/iterator.next.php
     * @return void
     */
    public function next() {
        $this->buffer = NULL;
    }
    
    /**
     * Rewind has no applicability for socket streams but must be included for
     * the stream to function as an Iterator
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void
     */
    public function rewind() {
        return NULL;
    }

    /**
     * @link http://php.net/manual/en/iterator.valid.php
     * @return bool
     */
    public function valid() {
        if ($isEof = @feof($this->socket)) {
            $this->notify(Events::DONE);
        }
        
        return !$isEof;
    }
    
    protected function isAlive() {
        return (is_resource($this->socket) && !@feof($this->socket));
    }
    
    protected function doSelect($read, $write, $ex, $tvsec, $tvusec) {
        return @stream_select($read, $write, $ex, $tvsec, $tvusec);
    }
    
    public function close() {
        @fclose($this->socket);
    }
    
    public function add($data) {
        if (empty($data) && $data !== '0') {
            return 0;
        }
        
        if (FALSE === ($bytes = @fwrite($this->socket, $data))) {
            $errorInfo = error_get_last();
            $this->notify(Events::ERROR, new StreamException(
                'Socket write failure: ' . $errorInfo['message']
            ));
        }
        
        return $bytes;
    }
    
    public function setGranularity($bytes) {
        $this->granularity = filter_var($bytes, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 8192,
                'min_range' => 1
            )
        ));
        
        return $this;
    }
    
    protected function getBuffer() {
        return $this->buffer;
    }
}