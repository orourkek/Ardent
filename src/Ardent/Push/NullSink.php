<?php

namespace Ardent\Push;

class NullSink implements Sink {
    
    function add($data) {
        return NULL;
    }
}