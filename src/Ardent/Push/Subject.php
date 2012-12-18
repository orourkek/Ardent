<?php

namespace Ardent\Push;

abstract class Subject extends Filter implements Observable {
    
    private $subscribers;
    
    public function subscribe(array $eventListenerMap, $unsubscribeOnError = true) {
        if (!$this->subscribers) {
            $this->subscribers = new \SplObjectStorage;
        }
        
        $subscription = new Subscription($this, $eventListenerMap, $unsubscribeOnError);
        $this->subscribers->attach($subscription);
        
        return $subscription;
    }
    
    public function unsubscribe(Subscription $subscription) {
        $this->subscribers->detach($subscription);
    }
    
    public function unsubscribeAll() {
        $this->subscribers = new \SplObjectStorage;
    }
    
    public function notify($event, $data = null) {
        if (!$this->subscribers) {
            return;
        } elseif (!$this->subscribers->count()) {
            return;
        } else {
            $data = $event === Events::DATA ? $this->applyFilters($data) : $data;
            foreach ($this->subscribers as $subscription) {
                call_user_func($subscription, $event, $data);
            }
        }
    }
}