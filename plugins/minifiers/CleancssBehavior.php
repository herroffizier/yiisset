<?php

class CleancssBehavior extends YiissetBaseBehavior {

    /**
     * Следует ли оптимизировать css-файлы.
     * 
     * @var boolean
     */
    public $optimizeCssFiles = true;
    
    /**
     * Путь к минификатору clean-css.
     *
     * Требует корректного значения {@link nodeExec}
     * и {@link optimizeCssFiles} = true.
     * 
     * @var string
     */
    public $cleancssExec = '/usr/local/bin/cleancss';

    /**
     * Аргументы для запуска clean-css.
     * 
     * @var string
     */
    public $cleancssArgs = '--skip-import --skip-rebase --compatibility ie7';

    public function init()
    {
        if (!$this->optimizeCssFiles) return;

        if ($this->cleancssExec && !$this->owner->nodeExec) {
            Yii::trace('No node.js executable found, disabling clean-css optimization');
            $this->cleancssExec = false;
        }

        if ($this->cleancssExec && !file_exists($this->cleancssExec)) {
            Yii::trace('No clean-css executable found, disabling clean-css optimization');
            $this->cleancssExec = false;
        }

        if (!$this->cleancssExec) {
            $this->cleancssExec = false;
            return;
        }

        $this->owner->features[] = 'cleancss';

        $this->owner->onOptimization->add(function(CEvent $event) {
            if ($event->params['type'] !== 'css') return;

            $this->cleancssFiles();
        });
    }

    /**
     * Сжать файл стилей при помощи clean-css.
     * 
     * @param  string $file
     */
    protected function cleancssFile($file)
    {
        $cmd = $this->owner->nodeExec.' '.escapeshellarg($this->cleancssExec).($this->cleancssArgs ? ' '.$this->cleancssArgs : '').' -o #TO_FILE# #FROM_FILE#';
        $this->owner->processFile('clean-css', $cmd, $file);
    }

    /**
     * Сжать все стили при помощи clean-css.
     */
    protected function cleancssFiles()
    {
        if (empty($this->owner->cssFiles)) return;

        $this->owner->startCounters('optimizing optimizing-css');

        foreach ($this->owner->cssFiles as $url => $media) {
            if (!($path = $this->owner->getLocalPath($url))) continue;
            $this->cleancssFile($path);
        }

        $this->owner->stopCounters('optimizing optimizing-css');
    }

}