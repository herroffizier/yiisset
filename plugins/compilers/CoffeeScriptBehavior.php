<?php

class CoffeeScriptBehavior extends YiissetBaseBehavior {

    /**
     * Путь к компилятору CoffeeScript.
     * Если путь корректен, все подключённые файлы с расширением .coffee будут
     * скомпилированы в javascript.
     * Стоит обратить внимание, что на данный момент файлы обрабатываются по одному.
     * 
     * Требует корректного значения {@link nodeExec}.
     * 
     * @var mixed
     */
    public $coffeeScriptExec = '/usr/local/bin/coffee';

    public function init()
    {
        if ($this->coffeeScriptExec && !$this->owner->nodeExec) {
            Yii::trace('No node.js executable found, disabling CoffeeScript compilation');
            $this->coffeeScriptExec = false;
        }

        if ($this->coffeeScriptExec && !file_exists($this->coffeeScriptExec)) {
            Yii::trace('No CoffeeScript executable found, disabling CoffeeScript compilation');
            $this->coffeeScriptExec = false;
        }

        if (!$this->coffeeScriptExec) {
            $this->coffeeScriptExec = false;
            return;
        }

        $this->owner->features[] = 'coffeescript compiling';

        $this->owner->onBeforeOptimization->add(function(YiissetEvent $event) {
            if ($event->type !== 'js') return;

            $this->compileCoffeeScriptFiles($event->position);
        });
    }

    /**
     * Скомпилировать CoffeeScript.
     * В случае успешной компиляции исходный файл будет удалён.
     * 
     * @param  string $file
     */
    protected function compileCoffeeScriptFile($file)
    {
        $cmd = 
            $this->owner->nodeExec.' '.escapeshellarg($this->coffeeScriptExec)
            .' -o '.escapeshellarg(dirname($file).'/')
            .' -c #FROM_FILE#';

        $compiledScriptFile = mb_substr($file, 0, mb_strlen($file) - 6).'js';

        $this->owner->processFile('CoffeeScript', $cmd, $file, $compiledScriptFile);
    }

    /**
     * Скомпилировать каждый .coffee файл в указанной позиции в JS.
     * 
     * @param  int $position
     */
    protected function compileCoffeeScriptFiles($position)
    {
        if (empty($this->owner->scriptFiles[$position])) return;
        
        $scriptFiles = array();
        
        foreach ($this->owner->scriptFiles[$position] as $url => $attributes) {
            if (
                !preg_match('/\.coffee$/', $url) 
                || !($path = $this->owner->getLocalPath($url))
            ) {
                $scriptFiles[$url] = $attributes;
                continue;
            }

            $this->owner->startCounters('coffee');

            $compiledScript = mb_substr($url, 0, mb_strlen($url) - 6).'js';
            $compiledScriptFile = mb_substr($path, 0, mb_strlen($path) - 6).'js';

            $this->compileCoffeeScriptFile($path);
            $scriptFiles[$compiledScript] = $attributes;

            $this->owner->stopCounters('coffee');
        }

        $this->owner->scriptFiles[$position] = $scriptFiles;
    }

}