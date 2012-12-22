<?php

namespace Ardent\Push;

/**
 * A non-blocking TCP socket server with full SSL support
 */
class TcpServer extends Subject {
    
    const STATE_STOPPED = 0;
    const STATE_STARTED = 1;
    
    const EVENT_STOP = 100;
    const EVENT_START = 200;
    const EVENT_CLIENT = 250;
    const EVENT_READABLE = 300;
    const EVENT_WRITEABLE = 400;
    const EVENT_LOOP_ITER = 500;
    const EVENT_ERROR = 900;
    
    const ATTR_MAX_CONN_CONCURRENCY = 'attrMaxConnConcurrency';
    const ATTR_IDLE_TIMEOUT = 'attrIdleTimeout';
    const ATTR_SELECT_SEC = 'attrSelectSec';
    const ATTR_SELECT_USEC = 'attrSelectUsec';
    const ATTR_SSL_ENABLED = 'attrSslEnabled';
    const ATTR_SSL_CRYPTO_TYPE = 'attrSslCryptoType';
    const ATTR_SSL_CERT_FILE = 'attrSslCertFile';
    const ATTR_SSL_CERT_PASS = 'attrSslCertPass';
    const ATTR_SSL_ALLOW_SELF_SIGNED = 'attrSslAllowSelfSigned';
    const ATTR_SSL_VERIFY_PEER = 'attrSslVerifyPeer';
    
    private $host;
    private $port;
    private $socket;
    private $clients = array();
    private $clientsPendingCrypto = array();
    private $isCryptoEnabled = FALSE;
    private $state = self::STATE_STOPPED;
    
    private $attributes = array(
        self::ATTR_MAX_CONN_CONCURRENCY => 50,
        self::ATTR_IDLE_TIMEOUT => 5,
        self::ATTR_SELECT_SEC => 0,
        self::ATTR_SELECT_USEC => 150,
        self::ATTR_SSL_ENABLED => FALSE,
        self::ATTR_SSL_CRYPTO_TYPE => STREAM_CRYPTO_METHOD_TLS_SERVER,
        self::ATTR_SSL_CERT_FILE => '',
        self::ATTR_SSL_CERT_PASS => '',
        self::ATTR_SSL_ALLOW_SELF_SIGNED => TRUE,
        self::ATTR_SSL_VERIFY_PEER => FALSE
    );
    
    /**
     * Note: When specifying a numerical IPv6 address (e.g. fe80::1), you must enclose the IP in 
     * square brackets—for example, [fe80::1].
     * 
     * @param int $port The port on which to accept new client connections
     * @param string $host The IP address to which the socket server should be bound
     */
    public function __construct($port, $host = '127.0.0.1') {
        $this->port = $port;
        // remove IPv6 brackets if present
        $this->host = rtrim($host, '[]');
    }
    
    /**
     * Stops the TCP server upon object destruction
     * 
     * @return void
     */
    public function __destruct() {
        $this->stop();
    }
    
    /**
     * Stop the TCP server
     * 
     * @return void
     */
    public function stop() {
        if ($this->state == self::STATE_STARTED) {
            foreach ($this->clients as $sockArr) {
                $sockArr['stream']->close();
            }
            $this->clients = array();
            
            @fclose($this->socket);
            
            $this->notify(self::EVENT_STOP);
        }
    }
    
    /**
     * Start the TCP server
     * 
     * @return void
     */
    public function start() {
        if ($this->state == self::STATE_STARTED) {
            return;
        }
        
        $host = filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? "[{$this->host}]" : $this->host;
        $uri = "tcp://{$host}:{$this->port}";
        
        if ($this->cryptoEnabled = $this->attributes[self::ATTR_SSL_ENABLED]) {
            $flags = STREAM_SERVER_BIND|STREAM_SERVER_LISTEN;
            $context = $this->buildSslContext();
            $socket = @stream_socket_server($uri, $errNo, $errStr, $flags, $context);
        } else {
            $socket = @stream_socket_server($uri, $errNo, $errStr);
        }
        
        if ($socket) {
            $this->socket = $socket;
            stream_set_blocking($socket, 0);
            $this->state = self::STATE_STARTED;
            $this->notify(self::EVENT_START);
            
            $this->listen();
            
        } else {
            $this->notify(self::EVENT_ERROR, new StreamException(
                $errStr,
                $errNo
            ));
        }
    }
    
    private function buildSslContext() {
        return stream_context_create(array('ssl' => array(
            'local_cert' => $this->attributes[self::ATTR_SSL_CERT_FILE],
            'passphrase' => $this->attributes[self::ATTR_SSL_CERT_PASS],
            'allow_self_signed' => $this->attributes[self::ATTR_SSL_ALLOW_SELF_SIGNED],
            'verify_peer' => $this->attributes[self::ATTR_SSL_VERIFY_PEER]
        )));
    }
    
    /**
     * Restart the TCP server
     * 
     * @return void
     */
    public function restart() {
        $this->stop();
        $this->start();
    }
    
    private function listen() {
        while (TRUE) {
            if ($this->isNewConnectionAllowed()) {
                $read = array($this->socket);
                $write = $ex = NULL;
                if (@stream_select($read, $write, $ex, 0, 0)) {
                    $this->accept();
                }
            }
            
            if (!empty($this->clientsPendingCrypto)) {
                $this->processPendingSslConns();
            }
            
            $arr = array();
            foreach ($this->clients as $sockId => $clientArr) {
                if (is_resource($clientArr['rawSock'])) {
                    $arr[] = $clientArr['rawSock'];
                }
            }
            
            $usec = $this->attributes[self::ATTR_SELECT_USEC];
            $read = $write = $arr;
            $ex = NULL;
            
            if (!empty($arr) && stream_select($read, $write, $ex, 0, $usec)) {
                
                foreach ($read as $rawSock) {
                    $sockId = (int) $rawSock;
                    
                    /**
                     * @var \Ardent\Push\Socket
                     */
                    $stream = $this->clients[$sockId]['stream'];
                    try {
                        $this->notify(self::EVENT_READABLE, $stream);
                    } catch (\Exception $e) {}
                }
                
                foreach ($write as $rawSock) {
                    $sockId = (int) $rawSock;
                    
                    /**
                     * @var \Ardent\Push\Socket
                     */
                    $stream = $this->clients[$sockId]['stream'];
                    
                    try {
                        $this->notify(self::EVENT_WRITEABLE, $stream);
                    } catch (\Exception $e) {}
                }
            }
            
            $this->closeIdleConnections();
            
            $this->notify(self::EVENT_LOOP_ITER);
        }
    }
    
    private function isNewConnectionAllowed() {
        $currentConnCount = count($this->clients);
        $maxAllowedConns = $this->attributes[self::ATTR_MAX_CONN_CONCURRENCY];
        
        return ($currentConnCount < $maxAllowedConns);
    }
    
    private function accept() {
        if (!$rawSock = @stream_socket_accept($this->socket, 0)) {
            $err = error_get_last();
            $this->notify(self::EVENT_ERROR, new StreamException(
                'Socket accept failure: ' . $err['message'],
                $err['type']
            ));
            
            return;
        }
        
        stream_set_blocking($rawSock, 0);
        
        $sockArr = array(
            'stream' => NULL,
            'rawSock' => $rawSock,
            'connectedAt' => microtime(TRUE),
            'lastReadAt' => NULL
        );
        
        if ($this->cryptoEnabled) {
            $sockId = (int) $rawSock;
            $this->clientsPendingCrypto[$sockId] = $sockArr;
        } else {
            $this->finalizeNewClient($sockArr);
        }
    }
    
    private function finalizeNewClient(array $sockArr) {
        $rawSock = $sockArr['rawSock'];
        $sockId = (int) $rawSock;
        $stream = new Socket($rawSock);
        $sockArr['stream'] = $stream;
        $this->clients[$sockId] = $sockArr;
        
        $clients = &$this->clients;
        $stream->subscribe(array(Observable::DATA => function() use ($clients, $sockId) {
            $this->clients[$sockId]['lastReadAt'] = time();
        }), FALSE);
        
        $this->notify(self::EVENT_CLIENT, $stream);
    }
    
    private function processPendingSslConns() {
        $cryptoType = $this->attributes[self::ATTR_SSL_CRYPTO_TYPE];
        
        foreach ($this->clientsPendingCrypto as $sockId => $sockArr) {
            $rawSock = $sockArr['rawSock'];
            $isCryptoEnabled = @stream_socket_enable_crypto($rawSock, TRUE, $cryptoType);
            
            if ($isCryptoEnabled == TRUE) {
                $this->finalizeNewClient($sockArr);
                unset($this->clientsPendingCrypto[$sockId]);
            } elseif ($isCryptoEnabled === FALSE) {
                $err = error_get_last();
                $this->notify(self::EVENT_ERROR, new StreamException(
                    'Socket SSL crypto failure: ' . $err['message'],
                    $err['type']
                ));
                unset($this->clientsPendingCrypto[$sockId]);
            }
        }
    }
    
    private function closeIdleConnections() {
        $now = time();
        $maxAllowableIdleTime = $this->attributes[self::ATTR_IDLE_TIMEOUT];
        
        foreach ($this->clients as $sockId => $sockArr) {
            $idleTime = $sockArr['lastReadAt']
                ? $now - $sockArr['lastReadAt']
                : $now - $sockArr['connectedAt'];
            
            if ($idleTime >= $maxAllowableIdleTime) {
                /**
                 * @var \Ardent\Push\Socket
                 */
                $stream = $sockArr['stream'];
                $stream->close();
                unset($this->clients[$sockId]);
            }
        }
    }
    
    /**
     * Assign a server attribute value
     * 
     * param int $attribute
     * param mixed $value
     * throws \Ardent\KeyException On invalid server attribute
     * return void
     */
    public function setAttribute($attribute, $value) {
        if (!array_key_exists($attribute, $this->attributes)) {
            throw new \Ardent\KeyException(
                "Invalid attribute: {$attribute} does not exist"
            );
        }
        
        $setterMethod = 'set' . ucfirst($attribute);
        if (method_exists($this, $setterMethod)) {
            $this->$setterMethod($value);
        } else {
            $this->attributes[$attribute] = $value;
        }
    }
    
    /**
     * Assign multiple server attributes at once
     * 
     * param mixed $arrayOrTraversable A key-value traversable holding server attribute values
     * throws \Ardent\TypeException On non-traversable attribute list
     * return void
     */
    public function setAllAttributes($arrayOrTraversable) {
        if (!(is_array($arrayOrTraversable) || $arrayOrTraversable instanceof \Traversable)) {
            throw new \Ardent\TypeException(
                get_class($this) . '::setAllAttributes expects an array or Traversable at Argument 1'
            );
        }
        
        foreach ($arrayOrTraversable as $attribute => $value) {
            $this->setAttribute($attribute, $value);
        }
    }

}
