<?php

namespace Ardent\Streams;

interface Sink {
    function filter($callback);
    function add($data);
}