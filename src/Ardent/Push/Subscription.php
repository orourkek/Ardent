<?php

namespace Ardent\Push;

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
        $this->assignHandlers($callbacks);
        $this->unsubscribeOnError = filter_var($unsubscribeOnError, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param array $callbacks A key-value array mapping events to callable listeners
     * @throws \Ardent\EmptyException On empty callback array
     * @throws \Ardent\FunctionException On invalid listener callback(s)
     * @return void
     */
    private function assignHandlers(array $callbacks) {
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
        if ($this->unsubscribeOnError && $event == Observable::ERROR) {
            $this->unsubscribe();
        }
    }
}