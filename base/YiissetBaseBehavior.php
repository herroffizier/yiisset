<?php

abstract class YiissetBaseBehavior extends CBehavior {
    
    public function events()
    {
        return array(
            'onInit' => 'init',
        );
    }

    public function init()
    {
    }

}