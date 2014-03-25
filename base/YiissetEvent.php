<?php

class YiissetEvent extends CEvent {
    
    public $type = null;
    public $position = null;

    public function __construct($sender, $type, $position)
    {
        $this->type = $type;
        $this->position = $position;

        parent::__construct($sender);
    }

}