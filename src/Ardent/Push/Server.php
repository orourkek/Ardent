<?php

namespace Ardent\Push;

class Server extends Subject {
    
    const STATE_STOPPED = 0;
    const STATE_RUNNING = 1;
    
    const EVENT_STOP = 100;
    const EVENT_START = 200;
    const EVENT_CLIENT = 250;
    const EVENT_READABLE = 300;
    const EVENT_WRITEABLE = 400;
    const EVENT_LOOP_ITER = 500;
    const EVENT_ERROR = 900;
    
    const ATTR_MAX_CONNECTIONS = 'attrMaxConnections';
    const ATTR_IDLE_TIMEOUT = 'attrIdleTimeout';
    const ATTR_SELECT_TVUSEC = 'attrSelectTvusec';
    
    private $scheme;
    private $host;
    private $port;
    private $socket;
    private $resourceMap = array();
    private $state = self::STATE_STOPPED;
    
    private $attributes = array(
        self::ATTR_MAX_CONNECTIONS => 50,
        self::ATTR_IDLE_TIMEOUT => 15,
        self::ATTR_SELECT_TVUSEC => 250
    );
    
    public function __construct($scheme, $port, $host = '127.0.0.1') {
        $this->scheme = strtolower($scheme);
        $this->port = $port;
        $this->host = $host;
    }
    
    public function __destruct() {
        $this->stop();
    }
    
    public function stop() {
        if ($this->state == self::STATE_RUNNING) {
            foreach ($this->resourceMap as $stream) {
                $stream->close();
            }
            $this->resourceMap = array();
            
            @stream_socket_shutdown($this->socket, STREAM_SHUT_RD);
            @fclose($this->socket);
            
            $this->notify(self::EVENT_STOP);
        }
    }
    
    public function start() {
        if ($this->state == self::STATE_RUNNING) {
            return;
        }
        
        $uri = "{$this->scheme}://{$this->host}:{$this->port}";
        
        if ($this->scheme == 'tcp') {
            $socket = @stream_socket_server($uri, $errNo, $errStr);
        } else {
            $flags = STREAM_SERVER_LISTEN | STREAM_SERVER_BIND;
            $socket = @stream_socket_server($uri, $errNo, $errStr, $flags);
        }
        
        if ($socket) {
            $this->state = self::STATE_RUNNING;
            $this->socket = $socket;
            stream_set_blocking($socket, 0);
            $this->notify(self::EVENT_START);
            
            $this->listen();
            
        } else {
            $this->notify(self::EVENT_ERROR, new StreamException(
                $errStr,
                $errNo
            ));
        }
    }
    
    public function restart() {
        $this->stop();
        $this->start();
    }
    
    private function listen() {
        while (TRUE) {
            $tvusec = $this->attributes[self::ATTR_SELECT_TVUSEC];
            
            if ($this->isNewConnectionAllowed()) {
                $read = array($this->socket);
                $write = $ex = NULL;
                if (@stream_select($read, $write, $ex, 0, $tvusec)) {
                    $this->accept();
                }
            }
            
            $read = $write = array_map(function($x) { return $x['sock']; }, $this->resourceMap);
            $ex = NULL;
            
            if ($read) {
                if (@stream_select($read, $write, $ex, 0, $tvusec)) {
                    $this->accept();
                }
                
                foreach ($read as $sock) {
                    $sockId = (int) $sock;
                    /**
                     * @var \Ardent\Push\Socket
                     */
                    $stream = $this->resourceMap[$sockId]['stream'];
                    try {
                        $this->notify(self::EVENT_READABLE, $stream);
                    } catch (\Exception $e) {}
                }
                
                foreach ($write as $sock) {
                    $sockId = (int) $sock;
                    /**
                     * @var \Ardent\Push\Socket
                     */
                    $stream = $this->resourceMap[$sockId]['stream'];
                    try {
                        $this->notify(self::EVENT_WRITEABLE, $stream);
                    } catch (\Exception $e) {}
                }
            }
            
            $this->clearDeadConnections();
            $this->closeIdleConnections();
            
            $this->notify(self::EVENT_LOOP_ITER);
        }
    }
    
    private function isNewConnectionAllowed() {
        $currentConnCount = count($this->resourceMap);
        $maxAllowedConns = $this->attributes[self::ATTR_MAX_CONNECTIONS];
        
        return ($currentConnCount < $maxAllowedConns);
    }
    
    private function accept() {
        if ($sock = @stream_socket_accept($this->socket, 0)) {
            $stream = new Socket($sock);
            $sockId = (int) $sock;
            
            $now = microtime(TRUE);
            
            $this->resourceMap[$sockId] = array(
                'stream' => $stream,
                'sock' => $sock,
                'connectedAt' => $now,
                'lastReadAt' => NULL
            );
            
            $resourceMap = &$this->resourceMap;
            
            $sockErrorListener = function() use ($resourceMap, $stream, $sockId) {
                $stream->close();
                unset($this->resourceMap[$sockId]);
            };
            
            $sockCloseListener = function() use ($resourceMap, $sockId) {
                unset($this->resourceMap[$sockId]);
            };
            
            $sockReadListener = function() use ($resourceMap, $sockId) {
                $this->resourceMap[$sockId]['lastReadAt'] = time();
            };
            
            $stream->subscribe(array(
                Observable::CLOSE => $sockCloseListener,
                Observable::ERROR => $sockErrorListener,
                Observable::DATA => $sockReadListener
            ));
            
            $this->notify(self::EVENT_CLIENT, $stream);
            
        } else {
            $err = error_get_last();
            $this->notify(self::EVENT_ERROR, new StreamException(
                'Socket accept failure: ' . $err['message'],
                $err['type']
            ));
        }
    }
    
    private function clearDeadConnections() {
        foreach ($this->resourceMap as $sockId => $sockArr) {
            if (!is_resource($sockArr['sock'])) {
                /**
                 * @var \Ardent\Push\Socket
                 */
                $stream = $sockArr['stream'];
                $stream->close();
            }
        }
    }
    
    private function closeIdleConnections() {
        $now = time();
        $maxAllowableIdleTime = $this->attributes[self::ATTR_IDLE_TIMEOUT];
        
        foreach ($this->resourceMap as $sockId => $sockArr) {
            $idleTime = $now - $sockArr['lastReadAt'];
            
            if ($idleTime >= $maxAllowableIdleTime) {
                /**
                 * @var \Ardent\Push\Socket
                 */
                $stream = $sockArr['stream'];
                $stream->close();
            }
        }
    }
}