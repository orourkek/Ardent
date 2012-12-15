<?php

namespace Ardent\Streams;

class TcpSsl extends Tcp {
    
    const CONN_PENDING_CRYPTO = 110;
    
    private $sslOptions = array(
        'mode' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
        'verifyPeer' => false,
        'allowSelfSigned' => false,
        'caFile' => null,
        'caPath' => null,
        'localCert' => '',
        'localCertPassphrase' => null,
        'cnMatch' => null,
        'verifyDepth' => 5,
        'ciphers' => 'DEFAULT'
    );
    
    public function setSslOptions($optArr) {
        $this->sslOptions = array_merge($this->sslOptions, $optArr);
    }
    
    public function current() {
        $buffer = $this->getBuffer();
        if (null !== $buffer) {
            return $buffer;
        }
        
        switch ($this->state) {
            case self::CONN_NONE:
                $this->connect();
                break;
            case self::CONN_PENDING:
                $read = $ex = array();
                $write = array($this->socket);
                if ($this->doSelect($read, $write, $ex, 0, 0)) {
                    $this->state = self::CONN_PENDING_CRYPTO;
                }
                break;
            case self::CONN_PENDING_CRYPTO:
                if ($this->enableCrypto()) {
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
        
        return null;
    }
    
    private function enableCrypto() {
        $crypto = @stream_socket_enable_crypto($this->socket, true, $this->sslOptions['mode']);
        
        if ($crypto) {
            return true;
        } elseif (false === $crypto) {
            $errorInfo = error_get_last();
            $this->notify(Events::ERROR, new StreamException(
                'SSL connect failure: ' . $errorInfo['message']
            ));
        }
        
        return false;
    }
    
    protected function buildContext() {
        $opts = array(
            'verify_peer' => $this->sslOptions['verifyPeer'],
            'allow_self_signed' => $this->sslOptions['allowSelfSigned'],
            'verify_depth' => $this->sslOptions['verifyDepth'],
            'cafile' => $this->sslOptions['caFile'],
            'ciphers' => $this->sslOptions['ciphers']
        );
        
        if ($cnMatch = $this->sslOptions['cnMatch']) {
            $opts['cnMatch'] = $cnMatch;
        }
        if ($caDirPath = $this->sslOptions['caPath']) {
            $opts['capath'] = $caDirPath;
        }
        if ($localCert = $this->sslOptions['localCert']) {
            $opts['local_cert'] = $localCert;
        }
        if ($localCertPassphrase = $this->sslOptions['localCertPassphrase']) {
            $opts['passphrase'] = $localCertPassphrase;
        }
        
        return stream_context_create(array('ssl' => $opts));
    }
    
}