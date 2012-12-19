<?php

namespace Ardent\Push;

class Socket extends Stream {
    
    const CONN_NONE = 0;
    const CONN_PENDING = 1;
    const CONN_READY = 2;
    
    private $uri;
    private $scheme;
    private $granularity = 8192;
    private $currentCache;
    
    protected $socket;
    protected $state = self::CONN_NONE;
    
    public function __construct($uriOrSocket) {
        if (is_string($uriOrSocket)) {
            $this->setUri($uriOrSocket);
        } elseif (is_resource($uriOrSocket)) {
            $this->setSock($uriOrSocket);
        } else {
            throw new \Ardent\TypeException(
                get_class($this) . '::__construct expects a string URI or socket resource at ' .
                'Argument 1'
            );
        }
    }
    
    private function setUri($uri) {
        $uri = strtolower($uri);
        
        if (!$uriParts = @parse_url($uri)) {
            throw new \Ardent\DomainException(
                'Invalid socket URI'
            );
        } elseif (empty($uriParts['scheme']) ||
            !($uriParts['scheme'] == 'tcp' || $uriParts['scheme'] == 'udp')
        ) {
            throw new \Ardent\DomainException(
                'Invalid socket URI scheme'
            );
        } elseif (empty($uriParts['port'])) {
            throw new \Ardent\DomainException(
                'Invalid socket URI port'
            );
        }
        
        $this->uri = $uriOrSocket;
        $this->scheme = $uriParts['scheme'];
    }
    
    protected function setSock($sock) {
        $meta = stream_get_meta_data($sock);
        
        if (empty($meta['stream_type'])) {
            throw new \Ardent\TypeException(
                'Invalid socket resource; TCP or UDP stream required'
            );
        } elseif ($meta['stream_type'] == 'tcp_socket/ssl') {
            $this->scheme = 'tcp';
        } elseif ($meta['stream_type'] == 'udp_socket') {
            $this->scheme = 'udp';
        } else {
            throw new \Ardent\TypeException(
                'Invalid socket resource; TCP or UDP stream required'
            );
        }
        
        $this->uri = $meta['stream_type'] . '://' . stream_socket_get_name($sock, true);
        $this->socket = $sock;
        $this->state = self::CONN_READY;
        stream_set_blocking($sock, 0);
    }
    
    public function __destruct() {
        $this->close();
    }
    
    public function close() {
        @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        @fclose($this->socket);
        $this->socket = NULL;
        $this->notify(Observable::CLOSE);
    }
    
    /**
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Current data or NULL on failure
     */
    public function current() {
        if (NULL !== $this->currentCache) {
            return $this->currentCache;
        }
        
        switch ($this->state) {
            case self::CONN_NONE:
                $this->connect();
                break;
            case self::CONN_PENDING:
                $write = array($this->socket);
                $read = $ex = NULL;
                if ($this->doSelect($read, $write, $ex, 0, 0)) {
                    $this->state = self::CONN_READY;
                    $this->notify(Observable::READY);
                }
                break;
            case self::CONN_READY:
                $read = array($this->socket);
                $write = $ex = NULL;
                if (!$this->doSelect($read, $write, $ex, 0, 0)) {
                    break;
                }
                
                $data = $this->read();
                
                if ($data || $data === '0') {
                    $this->currentCache = $data;
                    $this->notify(Observable::DATA, $data);
                    
                    return $data;
                }
                break;
        }
        
        return NULL;
    }
    
    protected function connect() {
        list($socket, $errNo, $errStr) = $this->makeSocketStream();
        
        // A SOCKET_EWOULDBLOCK error means the socket is trying really hard to connect and that
        // we should continue on as if the connection was successful
        if (FALSE !== $socket || $errNo === SOCKET_EWOULDBLOCK) {
            $this->state = self::CONN_PENDING;
            $this->socket = $socket;
            stream_set_blocking($socket, 0);
        } else {
            $msg = "Socket connection failure: {$this->uri}";
            $msg .= $errNo ? "; [Error# $errNo] $errStr" : '';
            
            $this->notify(Observable::ERROR, new StreamException($msg));
        }
    }
    
    private function makeSocketStream() {
        $context = $this->buildContext();
        $socket = @stream_socket_client(
            $this->uri,
            $errNo,
            $errStr,
            42, // <--- value not used with ASYNC connections
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
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
            $data = $this->applyOutputFilters($data);
            return $data;
        } elseif (!is_resource($this->socket) || @feof($this->socket)) {
            $this->notify(Observable::ERROR, new StreamException(
                'Socket has gone away'
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
        
        $this->notify(Observable::ERROR, $e);

        return NULL;
    }

    /**
     * @link http://php.net/manual/en/iterator.next.php
     * @return void
     */
    public function next() {
        $this->currentCache = NULL;
    }
    
    /**
     * Rewind has no applicability for socket streams but must be included for
     * the stream to function as an Iterator
     * 
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
            $this->notify(Observable::DONE);
        }
        
        return !$isEof;
    }
    
    protected function doSelect($read, $write, $ex, $tvsec, $tvusec) {
        return @stream_select($read, $write, $ex, $tvsec, $tvusec);
    }
    
    /**
     * Write data to the socket sink
     * 
     * @param string $data
     * @param bool $block
     * @return int Returns the number of bytes written to the socket
     */
    public function add($data, $block = FALSE) {
        if (empty($data) && $data !== '0') {
            return 0;
        }
        
        $data = $this->applyInputFilters($data);
        
        if (!$block) {
            return $this->doSockWrite($data);
        }
        
        $bytesToWrite = strlen($data);
        $bytesWritten = 0;
        
        while ($bytesWritten < $bytesToWrite) {
            if ($bytes = $this->doSockWrite(substr($data, $bytesWritten))) {
                $bytesWritten += $bytes;
            } else {
                break;
            }
        }
        
        return $bytesWritten;
    }
    
    private function doSockWrite($data) {
        $bytes = @fwrite($this->socket, $data);
        
        if (FALSE === $bytes) {
            $errorInfo = error_get_last();
            $this->notify(Observable::ERROR, new StreamException(
                'Socket write failure: ' . $errorInfo['message']
            ));
            
            return 0;
        } else {
            return $bytes;
        }
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
    
    protected function getCurrentCache() {
        return $this->currentCache;
    }
}