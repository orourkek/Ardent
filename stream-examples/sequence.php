<?php

use Ardent\Push\Events,
    Ardent\Push\Sequence;

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Ardent\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = dirname(__DIR__) . "/src/$class.php";
        require $file;
    }
});


$stream = new Sequence(range(1, 5));
$subscription = $stream->subscribe([Events::DATA => function($data) {
    echo "\$data from subscription listener --> $data | ";
}]);

foreach ($stream as $data) {
    echo $data . " <-- \$data from loop\r\n";
}