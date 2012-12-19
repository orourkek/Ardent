<?php

use Ardent\Push\Events,
    Ardent\Push\Sequence,
    Ardent\Push\Memory,
    Ardent\Push\Buffer,
    Ardent\Push\StreamException;

require dirname(__DIR__) . '/vendor/autoload.php';


// Buffer is a "filter" that buffers emitted data up to the specified size so that
// downstream listeners won't be notified until the specified number of bytes are reached. This
// is also helpful for cutting down on the number of times `strtoupper` and `str_rot13` are called
// in this example because they're only invoked when the buffer actually emits.
$buffer = (new Buffer(10))->filter('strtoupper')->filter('str_rot13');


$stream = (new Sequence(range('a', 'z')))->filter($buffer);
$sink = new Memory;

$stream->subscribe([
    Observable::DATA => function($data) use ($sink) {
        $sink->add($data);
    },
    Observable::DONE => function() use ($buffer, $sink) {
        $sink->add($buffer->flush());
    }
]);


// This loop is equivalent to calling `$stream->loop();`
while ($stream->valid()) {
    $stream->current(); // if there's new data on the stream, broadcast an Observable::DATA event
    $stream->next();
} // <-- when the iteration completes ($stream->valid() === false), Observable::DONE is broadcast

$sink->rewind();

echo $sink; // <-- works like `stream_get_contents()` -- retrieves all remaining data in the stream
            // or sink after the current position (that's why we rewinded first). Only available for
            // byte-based streams (File/Memory/Temp/String) -- not available for `Sequence` because
            // the generic Sequence stream can contain any type of data structure and won't 
            // necessarily translate to a string format.