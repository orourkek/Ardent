<?php

namespace Ardent\Push;

interface Sink {
    function filter($callback);
    function add($data);
}