<?php

class YiissetDelayedJob extends YiiqBaseJob
{
    public $state;
    public $hash;
    
    public function run()
    {
        $cs = Yii::app()->clientScript;

        $cs->restoreState($this->state, $this->hash);

        $output = '';
        $cs->render($output);
    }

}