<?php

use Ardent\Push\String;

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Ardent\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = dirname(__DIR__) . "/src/$class.php";
        require $file;
    }
});



$stream = new String('OMG this is a streamable evented string');
foreach ($stream as $data) {
    echo $data;
}

echo PHP_EOL;

$stream->rewind();
$stream->seek(14);
// byte-based streams have __toString (equivalent to the native `stream_get_contents()`)
echo $stream;

echo PHP_EOL;

// byte-based streams have an optional "whence" parameter for seeking (SEEK_CUR (default), SEEK_START, SEEK_END)
$stream->seek(-6, SEEK_END);
echo $stream;

echo PHP_EOL;

$stream->rewind();
// byte-based streams can specify the granularity at which events occur ($stream->current())
// String streams default to a granularity of "1"
$stream->setGranularity(3);
var_dump($stream->current());

$stream->rewind();
$stream->setGranularity(1);
var_dump($stream->current());