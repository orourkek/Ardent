<?php

use Ardent\Push\Events,
    Ardent\Push\Memory,
    Ardent\Push\Socket,
    Ardent\Push\StreamException;

require dirname(__DIR__) . '/vendor/autoload.php';


class HeadersOnlyBuffer {
    private $buffer;
    private $headersCompleted = false;
    
    public function __invoke($data) {
        $this->buffer .= $data;
        
        if ($this->headersCompleted) {
            return null;
        }

        if (false !== ($headerEndPos = strpos($this->buffer, "\r\n\r\n"))) {
            $this->headersCompleted = true;
            $return = substr($this->buffer, 0, $headerEndPos + 4);
            $this->buffer = null;
            
            return $return;
        }
        
        return null;
    }
    
    public function getBuffer() {
        return $this->buffer;
    }
}

$googSock = (new Socket('tcp://www.google.com:80'))->filter(new HeadersOnlyBuffer);
$googSink = new Memory;

$yahooSock = (new Socket('tcp://www.yahoo.com:80'))->filter(new HeadersOnlyBuffer);
$yahooSink = new Memory;

$bingSock = (new Socket('tcp://www.bing.com:80'))->filter(new HeadersOnlyBuffer);
$bingSink = new Memory;

$streams = [
    'goog' => $googSock,
    'yahoo' => $yahooSock,
    'bing' => $bingSock
];

$googSock->subscribe([
    Observable::READY => function() use ($googSock) {
        $googSock->add('' .
            "GET / HTTP/1.1\r\n" .
            "Host: www.google.com\r\n" .
            "User-Agent: test\r\n" .
            "Connection: close\r\n\r\n", TRUE
        );
    },
    Observable::DATA => function($data) use ($googSink){
        $googSink->add($data, TRUE);
    },
    Observable::DONE => function() use ($googSink, &$streams) {
        $googSink->rewind();
        unset($streams['goog']);
    },
    Observable::ERROR => function(StreamException $e) use (&$streams) {
        unset($streams['goog']);
        throw $e;
    }
]);

$yahooSock->subscribe([
    Observable::READY => function() use ($yahooSock) {
        $yahooSock->add('' .
            "GET / HTTP/1.1\r\n" .
            "Host: www.yahoo.com\r\n" .
            "User-Agent: test\r\n" .
            "Connection: close\r\n\r\n", TRUE
        );
    },
    Observable::DATA => function($data) use ($yahooSink){
        $yahooSink->add($data, TRUE);
    },
    Observable::DONE => function() use ($yahooSink, &$streams) {
        $yahooSink->rewind();
        unset($streams['yahoo']);
    },
    Observable::ERROR => function(StreamException $e) use (&$streams) {
        unset($streams['yahoo']);
        throw $e;
    }
]);

$bingSock->subscribe([
    Observable::READY => function() use ($bingSock) {
        $bingSock->add('' .
            "GET / HTTP/1.1\r\n" .
            "Host: www.bing.com\r\n" .
            "User-Agent: test\r\n" .
            "Connection: close\r\n\r\n", TRUE
        );
    },
    Observable::DATA => function($data) use ($bingSink){
        $bingSink->add($data, TRUE);
    },
    Observable::DONE => function() use ($bingSink, &$streams) {
        $bingSink->rewind();
        unset($streams['bing']);
    },
    Observable::ERROR => function(StreamException $e) use (&$streams) {
        unset($streams['bing']);
        throw $e;
    }
]);

$timeStart = microtime(TRUE);
while (TRUE) {
    if (empty($streams)) {
        break;
    }
    foreach ($streams as $key => $stream) {
        while ($stream->valid()) {
            if (NULL === $stream->current()) {
                break;
            }
            $stream->next();
        }
    }
    
    usleep(100);
}
$timeEnd = microtime(TRUE);

echo "\r\nGoogle Sink:\r\n=============================================\r\n";
echo $googSink;

echo "\r\nYahoo Sink:\r\n=============================================\r\n";
echo $yahooSink;

echo "\r\nBing Sink:\r\n=============================================\r\n";
echo $bingSink;

var_dump($timeEnd - $timeStart);
