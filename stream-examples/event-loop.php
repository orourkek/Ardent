<?php

use Ardent\Streams\Events,
    Ardent\Streams\Memory,
    Ardent\Streams\Socket,
    Ardent\Streams\StreamException;

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Ardent\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = dirname(__DIR__) . "/src/$class.php";
        require $file;
    }
});


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
    Events::READY => function() use ($googSock) {
        $googSock->add('' .
            "GET / HTTP/1.1\r\n" .
            "Host: www.google.com\r\n" .
            "User-Agent: test\r\n" .
            "Connection: close\r\n\r\n", TRUE
        );
    },
    Events::DATA => function($data) use ($googSink){
        $googSink->add($data, TRUE);
    },
    Events::DONE => function() use ($googSink, &$streams) {
        $googSink->rewind();
        unset($streams['goog']);
    },
    Events::ERROR => function(StreamException $e) use (&$streams) {
        unset($streams['goog']);
        throw $e;
    }
]);

$yahooSock->subscribe([
    Events::READY => function() use ($yahooSock) {
        $yahooSock->add('' .
            "GET / HTTP/1.1\r\n" .
            "Host: www.yahoo.com\r\n" .
            "User-Agent: test\r\n" .
            "Connection: close\r\n\r\n", TRUE
        );
    },
    Events::DATA => function($data) use ($yahooSink){
        $yahooSink->add($data, TRUE);
    },
    Events::DONE => function() use ($yahooSink, &$streams) {
        $yahooSink->rewind();
        unset($streams['yahoo']);
    },
    Events::ERROR => function(StreamException $e) use (&$streams) {
        unset($streams['yahoo']);
        throw $e;
    }
]);

$bingSock->subscribe([
    Events::READY => function() use ($bingSock) {
        $bingSock->add('' .
            "GET / HTTP/1.1\r\n" .
            "Host: www.bing.com\r\n" .
            "User-Agent: test\r\n" .
            "Connection: close\r\n\r\n", TRUE
        );
    },
    Events::DATA => function($data) use ($bingSink){
        $bingSink->add($data, TRUE);
    },
    Events::DONE => function() use ($bingSink, &$streams) {
        $bingSink->rewind();
        unset($streams['bing']);
    },
    Events::ERROR => function(StreamException $e) use (&$streams) {
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


















