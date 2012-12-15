<?php

namespace Ardent\Streams;

/**
 * A non-blocking, byte-based, in-memory stream of characters with variable data granularity
 * 
 * The main difference between the String stream and other byte-based streams is that the String
 * defaults to a granularity of 1 character.
 */
class String extends File {
    
    public function __construct($string) {
        $string = (string) $string;
        $uri = 'data://text/plain;base64,' . base64_encode($string);
        $this->setGranularity(1);
        parent::__construct($uri);
    }
}