<?php

namespace Ardent\Push;

/**
 * A generic, non-byte-based stream allowing stream/sink behavior for various data types.
 * 
 * Unlike the byte-based streams, NO Sequence::__toString() method exists because the streamable
 * data may not be appropriate for string output.
 */
class Sequence extends Stream implements \Ardent\CountableSeekableIterator {
    
    private $sequence;
    
    public function __construct($sequence = null) {
        $this->sequence = new \Ardent\LinkedList;
        
        if (empty($sequence)) {
            $this->notify(Events::READY);
            return;
        } elseif (!($sequence instanceof \Traversable || is_array($sequence))) {
            throw new \Ardent\TypeException(
                'Invalid sequence; array or Traversable expected'
            );
        } elseif (count($sequence)) {
            foreach ($sequence as $val) {
                $this->sequence->pushBack($val);
            }
            $this->sequence->rewind();
        }
        
        $this->notify(Events::READY);
    }

    /**
     * @link http://php.net/manual/en/countable.count.php
     * @return int
     */
    public function count() {
        return count($this->sequence);
    }

    /**
     * @link http://php.net/manual/en/seekableiterator.seek.php
     * @param int $position
     * @return bool
     */
    public function seek($position) {
        try {
            $this->sequence->seek($position);
            return TRUE;
        } catch (\Ardent\Exception $e) {
            return FALSE;
        }
    }

    /**
     * @param mixed $data
     * @return void
     */
    public function add($data) {
        if (count($this->sequence)) {
            $this->sequence->insertAfter($this->key(), $data);
        } else {
            $this->sequence->pushFront($data);
        }
        $this->sequence->next();
    }

    /**
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed
     */
    public function current() {
        $data = $this->sequence->current();
        $this->notify(Events::DATA, $data);
        
        return $data;
    }

    /**
     * @link http://php.net/manual/en/iterator.key.php
     * @return int
     */
    public function key() {
        return $this->sequence->key();
    }

    /**
     * @link http://www.php.net/manual/en/iterator.next.php
     * @return void
     */
    public function next() {
        $this->sequence->next();
    }

    /**
     * @link http://www.php.net/manual/en/iterator.rewind.php
     * @return void
     */
    public function rewind() {
        $this->sequence->rewind();
    }

    /**
     * @link http://www.php.net/manual/en/iterator.valid.php
     * @return bool
     */
    public function valid() {
        if (!$isValid = $this->sequence->valid()) {
            $this->notify(Events::DONE);
        }
        
        return $isValid;
    }
}