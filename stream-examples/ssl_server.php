<?php

/**
 * A basic SSL-enabled HTTP server -- returns a "Hello, World." response to all requests
 */

use Ardent\Push\TcpServer,
    Ardent\Push\Socket,
    Ardent\Push\StdOut;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Generates a self-signed PEM certificate for use in the SSL sever below
 */
function createSslCert($pemFile, $pemPassphrase, $pemDn) {
    // Create private key
    $privkey = openssl_pkey_new();

    // Create and sign CSR
    $cert = openssl_csr_new($pemDn, $privkey);
    $cert = openssl_csr_sign($cert, null, $privkey, 365);

    // Generate PEM file
    $pem = array();
    openssl_x509_export($cert, $pem[0]);
    openssl_pkey_export($privkey, $pem[1], $pemPassphrase);

    // Save PEM file
    file_put_contents($pemFile, implode($pem));
    chmod($pemFile, 0600);
}

$pemPassphrase = "42 is not a legitimate password";
$pemFile = __DIR__ . "/ssl-server-cacert.pem";
$pemDn = array(
    "countryName" => "US",                          // country name
    "stateOrProvinceName" => "SC",                  // state or province name
    "localityName" => "Myrtle Beach",               // your city name
    "organizationName" => "Your Mom",               // company name
    "organizationalUnitName" => "Your Department",  // department name
    "commonName" => "localhost",                    // full hostname
    "emailAddress" => "email@example.com"           // email address
);

if (!file_exists($pemFile)) {
    createSslCert($pemFile, $pemPassphrase, $pemDn);
}


/**
 * =================================================================================================
 * The above is unnecessary if you already have an existing PEM certificate
 * =================================================================================================
 */


$port = 9382;
$host = '127.0.0.1';
$server = new TcpServer($port, $host);

$server->setAllAttributes(array(
    TcpServer::ATTR_SSL_ENABLED => TRUE,
    TcpServer::ATTR_SSL_CERT_FILE => $pemFile,
    TcpServer::ATTR_SSL_CERT_PASS => $pemPassphrase
));

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
    TcpServer::EVENT_READABLE => function(Socket $sockStream) use ($log) {
        $log($sockStream->current());
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
 * After starting the server in a CLI environment, point your browser to https://localhost:9382
 * =================================================================================================
 */
 