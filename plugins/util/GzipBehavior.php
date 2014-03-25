<?php

class GzipBehavior extends YiissetBaseBehavior {
    
    /**
     * Следует ли создавать gzipped версии файлов.
     * Фича может быть полезна для некоторых серверов (например, для nginx).
     * Если нет более эффективных вариантов, для сжатия используется gzip.
     * 
     * @see http://nginx.org/ru/docs/http/ngx_http_gzip_static_module.html
     * @var boolean
     */
    public $saveGzippedCopy = true;

    /**
     * Путь к исполняемому файлу gzip.
     * 
     * @var string
     */
    public $gzipExec = '/usr/bin/gzip';

    /**
     * Аргументы для запуска gzip.
     * 
     * @var string
     */
    public $gzipArgs = '--best';

    /**
     * Путь к исполняемому файлу Zopfli.
     * Zopfli может быть использован в качестве более эффективной замены Gzip.
     * Если путь некорректен или равен false, Zopfli не будет использован.
     *
     * @see https://code.google.com/p/zopfli/
     * @var mixed 
     */
    public $zopfliExec = '/usr/bin/zopfli';

    /**
     * Аргументы для запуска Zopfli.
     * 
     * @var string
     */
    public $zopfliArgs = '-i15';

    public function init()
    {
        if ($this->gzipExec && !file_exists($this->gzipExec)) {
            Yii::trace('No Gzip executable found, disabling Gzip compression');
            $this->gzipExec = false;
        }

        if ($this->zopfliExec && !file_exists($this->zopfliExec)) {
            Yii::trace('No Zopfli executable found, disabling Zopfli compression');
            $this->zopfliExec = false;
        }

        if (!$this->gzipExec && !$this->zopfliExec && $this->saveGzippedCopy) {
            Yii::trace('No Gzip or Zopfli executables found, disabling gzip precomression');
            $this->saveGzippedCopy = false;
        }

        if (!$this->saveGzippedCopy) return;

        if ($this->zopfliExec) {
            $this->owner->features[] = 'zopfli precompressing';
        }
        else {
            $this->owner->features[] = 'gzip precompressing';
        }

        $this->owner->onAfterOptimization->add(function(YiissetEvent $event) {
            if ($event->type === 'css') {
                $this->createGzippedCssFiles();
            }
            else {
                $this->createGzippedScriptFiles($event->position);
            }
        });
    }

    /**
     * Создать gzipped копию файла.
     * Если возможно, используется Zopfli, в противном случае - gzip.
     * 
     * @param  string $file
     */
    protected function gzipFile($file)
    {
        if (!$this->zopfliExec && !$this->gzipExec) return;

        $gzippedFile = $file.'.gz';

        if ($this->zopfliExec) {
            $tool = 'Zopfli';
            $cmd = $this->zopfliExec.($this->zopfliArgs ? ' '.$this->zopfliArgs : '').' #FROM_FILE#';
        }
        else {
            $tool = 'Gzip';
            $cmd = $this->gzipExec.($this->gzipArgs ? ' '.$this->gzipArgs : '').' --stdout #FROM_FILE# > #TO_FILE#';
        }
        
        $this->owner->processFile($tool, $cmd, $file, $gzippedFile);
    }

    /**
     * Создать сжатые gzip'ом копии перечисленных файлов.
     *  
     * @param  array $files
     */
    protected function createGzippedCopies(array $files)
    {
        foreach ($files as $url => $attributes) {
            if (!($path = $this->owner->getLocalPath($url))) continue;
            $this->gzipFile($path);
        }
    }

    /**
     * Создать сжатые копии всех стилей.
     */
    protected function createGzippedCssFiles()
    {
        if (empty($this->owner->cssFiles)) return;

        $this->owner->startCounters('compressing compressing-css');
        $this->createGzippedCopies($this->owner->cssFiles);
        $this->owner->stopCounters('compressing compressing-css');
    }

    /**
     * Создать сжатые копии всех скриптов в указанной позиции.
     * 
     * @param  int $position
     */
    protected function createGzippedScriptFiles($position)
    {
        if (empty($this->owner->scriptFiles[$position])) return;

        $this->owner->startCounters('compressing compressing-js');
        $this->createGzippedCopies($this->owner->scriptFiles[$position]);
        $this->owner->stopCounters('compressing compressing-js');
    }

}