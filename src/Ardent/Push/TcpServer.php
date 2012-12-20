<?php

namespace Ardent\Push;

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
    
    const ATTR_MAX_CONNECTIONS = 'attrMaxConnections';
    const ATTR_IDLE_TIMEOUT = 'attrIdleTimeout';
    const ATTR_SELECT_TVUSEC = 'attrSelectTvusec';
    
    private $host;
    private $port;
    private $socket;
    private $clients = array();
    private $state = self::STATE_STOPPED;
    
    private $attributes = array(
        self::ATTR_MAX_CONNECTIONS => 50,
        self::ATTR_IDLE_TIMEOUT => 5,
        self::ATTR_SELECT_TVUSEC => 150
    );
    
    public function __construct($port, $host = '127.0.0.1') {
        $this->port = $port;
        $this->host = $host;
    }
    
    public function __destruct() {
        $this->stop();
    }
    
    public function stop() {
        if ($this->state == self::STATE_STARTED) {
            foreach ($this->clients as $sockArr) {
                $sockArr['stream']->close();
            }
            $this->clients = array();
            
            @stream_socket_shutdown($this->socket, STREAM_SHUT_RD);
            @fclose($this->socket);
            
            $this->notify(self::EVENT_STOP);
        }
    }
    
    public function start() {
        if ($this->state == self::STATE_STARTED) {
            return;
        }
        
        $uri = "tcp://{$this->host}:{$this->port}";
        $socket = @stream_socket_server($uri, $errNo, $errStr);
        
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
    
    public function restart() {
        $this->stop();
        $this->start();
    }
    
    private function listen() {
        $tvusec = $this->attributes[self::ATTR_SELECT_TVUSEC];
        
        while (TRUE) {
            if ($this->isNewConnectionAllowed()) {
                $read = array($this->socket);
                $write = $ex = NULL;
                if (@stream_select($read, $write, $ex, 0, $tvusec)) {
                    $this->accept();
                }
            }
            
            $read = $write = array_map(function($x) { return $x['sock']; }, $this->clients);
            $ex = NULL;
            
            if ($read && @stream_select($read, $write, $ex, 0, $tvusec)) {
                
                foreach ($read as $sock) {
                    $sockId = (int) $sock;
                    /**
                     * @var \Ardent\Push\Socket
                     */
                    $stream = $this->clients[$sockId]['stream'];
                    try {
                        $this->notify(self::EVENT_READABLE, $stream);
                    } catch (\Exception $e) {}
                }
                
                foreach ($write as $sock) {
                    $sockId = (int) $sock;
                    
                    /**
                     * @var \Ardent\Push\Socket
                     */
                    $stream = $this->clients[$sockId]['stream'];
                    
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
        $currentConnCount = count($this->clients);
        $maxAllowedConns = $this->attributes[self::ATTR_MAX_CONNECTIONS];
        
        return ($currentConnCount < $maxAllowedConns);
    }
    
    private function accept() {
        if ($sock = @stream_socket_accept($this->socket, 0)) {
            $stream = new Socket($sock);
            $sockId = (int) $sock;
            
            $this->clients[$sockId] = array(
                'stream' => $stream,
                'sock' => $sock,
                'connectedAt' => microtime(TRUE),
                'lastReadAt' => NULL
            );
            
            $clients = &$this->clients;
            $sockReadListener = function() use ($clients, $sockId) {
                $this->clients[$sockId]['lastReadAt'] = time();
            };
            
            $stream->subscribe(array(
                Observable::DATA => $sockReadListener
            ), FALSE);
            
            // non-blocking behavior fails without this short break
            usleep(100);
            
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
        foreach ($this->clients as $sockId => $sockArr) {
            if (!is_resource($sockArr['sock'])) {
                /**
                 * @var \Ardent\Push\Socket
                 */
                $stream = $sockArr['stream'];
                $stream->close();
                unset($this->clients[$sockId]);
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
}