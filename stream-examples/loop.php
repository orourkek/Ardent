<?php

use Ardent\Streams\Sequence,
    Ardent\Streams\Events;

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Ardent\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = dirname(__DIR__) . "/src/$class.php";
        require $file;
    }
});


/**
 * Streamable::loop is a shortcut method for iterating over the Streamable data.
 * 
 * Streams always maintain an internal pointer to their current position. The implication for 
 * Streamable::loop is that only the remaining data after the current position will be output
 * to subscribers. If the full contents of the stream are required, Streamable::rewind should be
 * invoked prior to iterating over the Streamable.
 * 
 * In practice, Streamable::loop is the same as manually iterating over the stream:
 * 
 *     while ($stream->valid()) {
 *         $stream->current();
 *         $stream->next();
 *     }
 * 
 */

$streamData = range('a', 'z');
$stream = new Sequence($streamData);

for ($i=0; $i<count($streamData)-3; $i++) {
    $stream->next();
}

$stream->subscribe([Events::DATA => function($data) { echo $data; }]);
$stream->loop();

echo PHP_EOL;

$stream->rewind();
$stream->loop();

echo PHP_EOL;