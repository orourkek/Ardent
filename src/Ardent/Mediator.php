<?php

namespace Ardent;

interface Mediator {

    /**
     * @param string $event
     * @param callable $callable
     *
     * @throws TypeException
     * @return void
     */
    function addListener($event, $callable);

    /**
     * @param string $event
     * @param callable $callable
     *
     * @return void
     */
    function removeListener($event, $callable);

    /**
     * @param string $event
     *
     * @return void
     */
    function removeEvent($event);

    /**
     * @return void
     */
    function clear();

    /**
     * @param string $event
     * @param ... $varargs
     * @return void
     */
    function notify($event, $varargs = NULL);

    /**
     * Note that this method should not generate warnings or errors when the
     * provided event does not exist.
     *
     * @param string $event
     * @return array
     */
    function getListeners($event);

    /**
     * @return array of event names
     */
    function getEvents();

}
