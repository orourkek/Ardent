<?php

use Ardent\Push\Server,
    Ardent\Push\Socket;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * After starting the server in a CLI environment, point your browser to http://localhost:9382
 */
$server = new Server('tcp', 9382);

$server->subscribe([
    Server::EVENT_START => function() { echo "~ SERVER STARTED ~\r\n"; },
    Server::EVENT_STOP => function() { echo "- SERVER STOPPED -\r\n"; },
    Server::EVENT_CLIENT => function(Socket $stream) { echo "+ $stream accepted at ".date('c')."\r\n"; },
    Server::EVENT_READABLE => function(Socket $stream) {
        $stream->current();
        $stream->next();
    },
    Server::EVENT_WRITEABLE => function(Socket $stream) use ($server) {
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