<?php

namespace Ardent\Streams;

class Subscription {
    
    /**
     * @var \SplObjectStorage
     */
    protected $subject;
    
    /**
     * @var array
     */
    protected $callbacks = array();
    
    /**
     * @var bool
     */
    protected $unsubscribeOnError;
    
    /**
     * @param Observable $subject The watchable object we're subscribing to
     * @param array $callbacks A key-value array mapping events to callable listeners
     * @param bool $unsubscribeOnError
     * @throws \Ardent\EmptyException On empty listener callback array
     * @throws \Ardent\FunctionException On invalid listener callback(s)
     */
    public function __construct(Observable $subject, array $callbacks, $unsubscribeOnError = true) {
        $this->subject = $subject;
        $this->assignAllHandlers($callbacks);
        $this->unsubscribeOnError = filter_var($unsubscribeOnError, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param array $callbacks A key-value array mapping events to callable listeners
     * @throws \Ardent\EmptyException On empty callback array
     * @throws \Ardent\FunctionException On invalid listener callback(s)
     * @return void
     */
    public function assignAllHandlers(array $callbacks) {
        if (empty($callbacks)) {
            throw new \Ardent\EmptyException(
                'No subscription listeners specified'
            );
        }
        foreach ($callbacks as $event => $callback) {
            if (is_callable($callback)) {
                $this->callbacks[$event] = $callback;
            } else {
                throw new \Ardent\FunctionException(
                    'Invalid subscription callback'
                );
            }
        }
    }
    
    /**
     * @param array $callback A callable listener for the READY event
     * @throws \Ardent\FunctionException On invalid listener callback
     * @return void
     */
    public function onReady($callback) {
        if (is_callable($callback)) {
            $this->callbacks[Events::READY] = $callback;
        } else {
            throw new \Ardent\FunctionException(
                'Invalid subscription callback'
            );
        }
    }
    
    /**
     * @param array $callback A callable listener for the DATA event
     * @throws \Ardent\FunctionException On invalid listener callback
     * @return void
     */
    public function onData($callback) {
        if (is_callable($callback)) {
            $this->callbacks[Events::DATA] = $callback;
        } else {
            throw new \Ardent\FunctionException(
                'Invalid subscription callback'
            );
        }
    }
    
    /**
     * @param array $callback A callable listener for the DONE event
     * @throws \Ardent\FunctionException On invalid listener callback
     * @return void
     */
    public function onDone($callback) {
        if (is_callable($callback)) {
            $this->callbacks[Events::DONE] = $callback;
        } else {
            throw new \Ardent\FunctionException(
                'Invalid subscription callback'
            );
        }
    }
    
    /**
     * @param array $callback A callable listener for the ERROR event
     * @throws \Ardent\FunctionException On invalid listener callback
     * @return void
     */
    public function onError($callback) {
        if (is_callable($callback)) {
            $this->callbacks[Events::ERROR] = $callback;
        } else {
            throw new \Ardent\FunctionException(
                'Invalid subscription callback'
            );
        }
    }
    
    /**
     * Remove this subscription from the watchable subject
     * 
     * @return void
     */
    public function unsubscribe() {
        $this->subject->unsubscribe($this);
    }
    
    /**
     * Invoke the registered subscription callback for the specified event
     * 
     * @param string $event
     * @param mixed $data
     * @return void
     */
    public function __invoke($event, $data = null) {
        if (!empty($this->callbacks[$event])) {
            call_user_func($this->callbacks[$event], $data);
        }
        if ($this->unsubscribeOnError && $event == Events::ERROR) {
            $this->unsubscribe();
        }
    }
}