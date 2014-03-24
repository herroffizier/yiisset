<?php

class NodeJsBehavior extends YiissetBaseBehavior {
    
    /**
     * Путь к исполняемому файлу node.js.
     * Node.js используется для ряда фич - например, для компиляции CoffeeScript.
     * Если путь некорректен или равен false, фичи, которым он нужен, не будут
     * использоваться.
     * 
     * @var mixed
     */
    public $nodeExec = '/usr/local/bin/node';

    public function init()
    {
        if (!$this->nodeExec || !file_exists($this->nodeExec)) {
            Yii::trace('No node.js executable found');
            $this->nodeExec = false;
        }
    }

}