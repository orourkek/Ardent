<?php

/**
 * A basic HTTP server -- returns a "Hello, World." response to all requests
 */

use Ardent\Push\TcpServer,
    Ardent\Push\Socket,
    Ardent\Push\StdOut,
    Ardent\Push\StreamException;

require dirname(__DIR__) . '/vendor/autoload.php';

$port = 9382;
$host = '127.0.0.1';
$server = new TcpServer($port, $host);
$server->setAttribute(TcpServer::ATTR_MAX_CONN_CONCURRENCY, 0);

$stdOut = new StdOut;
$log = function($data) use ($stdOut) { $stdOut->add($data); };

$server->subscribe([
    TcpServer::EVENT_START => function() use ($log) {
        $log("~ SERVER STARTED ~\r\n");
    },
    TcpServer::EVENT_STOP => function() use ($log) {
        $log("- SERVER STOPPED -\r\n");
    },
    TcpServer::EVENT_CLIENT => function(Socket $sockStream) use ($log) {
        $log("+ $sockStream accepted: " . date('r') . "\r\n");
    },
    TcpServer::EVENT_ERROR => function(StreamException $e) use ($log) {
        $log($e->getMessage() . "\r\n");
    },
    TcpServer::EVENT_READABLE => function(Socket $sockStream) use ($log) {
        $data = $sockStream->current();
        $log($data);
        $sockStream->next();
    },
    TcpServer::EVENT_WRITEABLE => function(Socket $sockStream) {
        $body = "Hello, world.";
        $sockStream->add(
            "HTTP/1.1 200 OK\r\n" .
            "Connection: close\r\n" .
            "Content-Length: ".strlen($body)."\r\n" .
            "\r\n" .
            "$body", TRUE
        );
        $sockStream->close();
    }
]);

$server->start();


/**
 * =================================================================================================
 * After starting the server in a CLI environment, point your browser to http://localhost:9382
 * =================================================================================================
 */