<?php

class LazyLoadBehavior extends YiissetBaseBehavior {

    /**
     * Следует ли использовать LazyLoad для подключения ресурсов на странице.
     * LazyLoad позволяет существенно сократить время загрузки страницы за счёт
     * параллельной загрузки различных ресурсов, что может быть крайне востребованным
     * при работе с медленными CDN (привет, Facebook).
     *
     * @see https://github.com/herroffizier/lazyload
     * @var boolean
     */
    public $useLazyLoad = true;

    /**
     * Был ли подключен скрипт LazyLoad.
     * 
     * @var boolean
     */
    protected $lazyLoadRegistered = false;

    public function init()
    {
        if (!$this->useLazyLoad) return;

        $this->owner->features[] = 'lazyload';

        $this->owner->onAfterOptimization->add(function(YiissetEvent $event) {
            $this->lazyLoadScriptFiles($event->position);
            if ($event->position === CClientScript::POS_HEAD) {
                $this->lazyLoadCssFiles();
            }
        });
    }
    
    /**
     * Зарегистрирвовать скрипт LazyLoad в указанной позиции.
     * 
     * @param  int $position
     */
    protected function registerLazyLoad($position)
    {
        if ($this->lazyLoadRegistered) return;
        $this->lazyLoadRegistered = true;

        $basePath = Yii::app()->assetManager->publish(Yii::getPathOfAlias('yiisset.assets'));
        $this->owner->registerScriptFile($basePath.'/'.(YII_DEBUG ? 'lazyload.js' : 'lazyload.min.js'), $position);

    }

    /**
     * Загружать все стили при помощи LazyLoad.
     */
    protected function lazyLoadCssFiles()
    {
        if (empty($this->owner->cssFiles) || count($this->owner->cssFiles) < 2) return;

        $this->owner->startCounters('lazyload lazyload-css');

        $cssFiles = array();
        foreach ($this->owner->cssFiles as $url => $media) {
            if ($media) {
                $cssFiles[] = array('url' => $url, 'media' => $media);
            }
            else {
                $cssFiles[] = $url;
            }
        }

        $code = 'LazyLoad.css('.CJSON::encode($cssFiles).');';
        $this->owner->registerScript('lazy_load_css', $code, CClientScript::POS_HEAD);

        $this->owner->cssFiles = array();

        $this->registerLazyLoad(CClientScript::POS_HEAD);

        $this->owner->stopCounters('lazyload lazyload-css');
    }

    /**
     * Загружать все скрипты в указанной позиции через LazyLoad.
     * При этом, если в указанной позиции помимо файлов есть инлайновые скрипты,
     * они будут выполнены только после того, как LazyLoad завершит свою работу.
     * 
     * @param  int $position
     */
    protected function lazyLoadScriptFiles($position)
    {
        if (!isset($this->owner->scriptFiles[$position]) || count($this->owner->scriptFiles[$position]) < 2) return;

        $this->owner->startCounters('lazyload lazyload-js');

        $scriptFiles = array();
        foreach($this->owner->scriptFiles[$position] as $url => $attributes) {
            if (is_array($attributes)) {
                $scriptFiles[] = array_merge($attributes, compact('url'));
            }
            else {
                $scriptFiles[] = $url;
            }
        }

        $inlineCode = $this->owner->getInlineCode($position);
        if ($inlineCode) {
            $this->owner->clearInlineCode($position);
        }

        $code = 'LazyLoad.js('.CJSON::encode($scriptFiles);
        if ($inlineCode) {
            $code .= ',function(){'.$inlineCode.';}';
        }
        $code .= ');';
        $this->owner->registerScript('lazyLoad_scripts_'.$position, $code, $position);

        $this->owner->scriptFiles[$position] = array();

        $this->registerLazyLoad($position);

        $this->owner->stopCounters('lazyload lazyload-js');
    }

}