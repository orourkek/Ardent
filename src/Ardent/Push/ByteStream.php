<?php

namespace Ardent\Push;

/**
 * Streams are Iterators with the additional property of "observability"
 */
interface ByteStream extends Streamable {
    
    /**
     * Return the stream's underlying resource
     */
    function getResource();
}