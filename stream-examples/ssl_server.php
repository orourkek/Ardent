<?php

use Ardent\Push\TcpServer,
    Ardent\Push\Socket;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Generates a self-signed PEM certificate (requires openssl ... duh!)
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
    $pem = implode($pem);

    // Save PEM file
    file_put_contents($pemFile, $pem);
    chmod($pemFile, 0600);
}

$pemPassphrase = "42 is not a legitimate password";
$pemFile = __DIR__ . "/temp-cert.pem";
$pemDn = array(
    "countryName" => "US",                          // country name
    "stateOrProvinceName" => "SC",                  // state or province name
    "localityName" => "Myrtle Beach",               // your city name
    "organizationName" => "Your Mom",               // company name
    "organizationalUnitName" => "Your Department",  // department name
    "commonName" => "localhost",                    // full hostname.
    "emailAddress" => "email@example.com"           // email address
);

if (!file_exists($pemFile)) {
    createSslCert($pemFile, $pemPassphrase, $pemDn);
}


/**
 * =================================================================================================
 * OMIT everything above this line if you already have an existing PEM certificate
 * =================================================================================================
 */


/**
 * After starting the server in a CLI environment, point your browser to https://localhost:9382
 */
$server = new TcpServer(9382);
$server->setAllAttributes(array(
    TcpServer::ATTR_SSL_ENABLED => TRUE,
    TcpServer::ATTR_SSL_CERT_FILE => $pemFile,
    TcpServer::ATTR_SSL_CERT_PASS => $pemPassphrase
));

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