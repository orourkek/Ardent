<?php

use Ardent\Push\StdOut,
    Ardent\Push\StdErr,
    Ardent\Push\Sequence;

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Ardent\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = dirname(__DIR__) . "/src/$class.php";
        require $file;
    }
});


$stream = new Sequence(range('a', 'z'));
$sink = new StdOut;

$sink->add("--- We're about to stream the alphabet to STDOUT from a Sequence stream:" . PHP_EOL);
$stdOutSubscription = $stream->pipe($sink);
sleep(1);
$stream->loop();


$sink->add(PHP_EOL);
$sink->add("--- Now switching to a STDERR pipe for alphabet output ..." . PHP_EOL);
sleep(1);

// remove the StdOut pipe subscription from the Sequence stream:
$stdOutSubscription->unsubscribe();
// or alternatively:
//$stream->unsubscribe($stdOutSubscription);
// or yet another way to do it:
//$stream->unsubscribeAll();

$stream->pipe(new StdErr);
$stream->rewind();
$stream->loop();