<?php

use Ardent\Push\Events,
    Ardent\Push\Sequence;

require dirname(__DIR__) . '/vendor/autoload.php';


$stream = new Sequence(range(1, 5));
$subscription = $stream->subscribe([Observable::DATA => function($data) {
    echo "\$data from subscription listener --> $data | ";
}]);

foreach ($stream as $data) {
    echo $data . " <-- \$data from loop\r\n";
}