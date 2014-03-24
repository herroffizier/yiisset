<?php

class UglifyJsBehavior extends YiissetBaseBehavior {
    
    /**
     * Следует ли отпимизировать скрипты.
     * 
     * @var boolean
     */
    public $optimizeScriptFiles = true;

    /**
     * Путь к минификатору uglify.js.
     * 
     * Требует корректного значения {@link nodeExec} 
     * и {@link optimizeScriptFiles} = true.
     * 
     * @var mixed
     */
    public $uglifyjsExec = '/usr/local/bin/uglifyjs';

    /**
     * Аргументы для запуска uglify.js.
     * 
     * @var string
     */
    public $uglifyjsArgs = '-cm';

    public function init()
    {
        if (!$this->optimizeScriptFiles) return;

        if ($this->uglifyjsExec && !$this->owner->nodeExec) {
            Yii::trace('No node.js executable found, disabling UglifyJS optimization');
            $this->uglifyjsExec = false;
        }

        if ($this->uglifyjsExec && !file_exists($this->uglifyjsExec)) {
            Yii::trace('No UglifyJS executable found, disabling UglifyJS optimization');
            $this->uglifyjsExec = false;
        }

        if (!$this->uglifyjsExec) {
            $this->uglifyjsExec = false;
            return;
        }

        $this->owner->features[] = 'uglifyjs';

        $this->owner->onOptimization->add(function(CEvent $event) {
            if (!$this->optimizeScriptFiles || $event->params['type'] !== 'js') return;

            $this->uglifyScriptFiles($event->params['position']);
        });
    }

    /**
     * Сжать скрипт при помощи Uglify.js.
     * 
     * @param  string $file
     */
    protected function uglifyFile($file)
    {
        $cmd = $this->owner->nodeExec.' '.escapeshellarg($this->uglifyjsExec).' #FROM_FILE#'.($this->uglifyjsArgs ? ' '.$this->uglifyjsArgs : '').' -o #TO_FILE#';
        $this->owner->processFile('Uglify.js', $cmd, $file);
    }

    /**
     * Сжать все скрипты в указанной позиции.
     * 
     * @param  string $position
     */
    public function uglifyScriptFiles($position)
    {
        if (empty($this->owner->scriptFiles[$position])) return;

        $this->owner->startCounters('optimizing optimizing-js');

        foreach ($this->owner->scriptFiles[$position] as $url => $attributes) {
            if (!($path = $this->owner->getLocalPath($url))) continue;
            $this->uglifyFile($path);
        }

        $this->owner->stopCounters('optimizing optimizing-js');
    }

}