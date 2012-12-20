<?php

use Ardent\Push\TcpServer,
    Ardent\Push\Socket;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * After starting the server in a CLI environment, point your browser to http://localhost:9382
 */
$server = new TcpServer(9382);

$server->subscribe([
    TcpServer::EVENT_START => function() { echo "~ SERVER STARTED ~\r\n"; },
    TcpServer::EVENT_STOP => function() { echo "- SERVER STOPPED -\r\n"; },
    TcpServer::EVENT_CLIENT => function(Socket $stream) { echo "+ $stream accepted: ".date('c')."\r\n"; },
    TcpServer::EVENT_READABLE => function(Socket $stream) {
        $stream->current();
        $stream->next();
    },
    TcpServer::EVENT_WRITEABLE => function(Socket $stream) use ($server) {
        $stream->add(
            "HTTP/1.1 200 OK\r\n" .
            "Connection: close\r\n" .
            "Content-Length: 13\r\n" .
            "\r\n" .
            "Hello, World.", TRUE
        );
        $stream->close();
    }
]);

$server->start();