<?php

class InlineScriptBehavior extends YiissetBaseBehavior {
    
    /**
     * Следует ли помещать все инлайновые скрипты в отдельный файл.
     * Довольно бесполезная фича, которая может пригодиться лишь отъявленным
     * борцам за чистоту кода страницы.
     * Если равна true, инлайновые скрипты будут раскиданы по нескольким файлам,
     * соответствующим позициям на странице.
     * 
     * @var boolean
     */
    public $disableInlineScripts = true;

    /**
     * Минимальный размер инлайнового скрипта в байтах, после которого скрипт 
     * будет сохранён в отдельный файл.
     * Если указан 0, скрипт любого размера будет сохранён в файл.
     * 
     * @var integer
     */
    public $inlineScriptSizeThreshold = 1024;

    public function init()
    {
        if (!$this->disableInlineScripts) return;

        $this->owner->features[] = 'saving inline code to file';

        $this->owner->onBeforeOptimization->add(function(YiissetEvent $event) {
            if ($event->type !== 'js') return;
            $this->saveInlineCodeToFile($event->position);
        });
    }

    /**
     * Сохранить инлайновые скрипты в указанной позиции в общий файл.
     * Довольно бесполезная фича, единственная цель которой - избавить тело страницы
     * от инлайновых скриптов.
     * 
     * @param  int $position
     */
    protected function saveInlineCodeToFile($position = self::POS_HEAD)
    {
        $code = $this->owner->getInlineCode($position);
        if (!$code) return;

        $this->owner->startCounters('saving-inline');

        if (!$this->inlineScriptSizeThreshold || strlen($code) >= $this->inlineScriptSizeThreshold) {
            $fileName = 'inline-' . $this->owner->hash($code) . '.js';
            $inlineFile = Yii::app()->assetManager->basePath . DIRECTORY_SEPARATOR . $fileName;
            $inlineUrl = Yii::app()->assetManager->baseUrl . DIRECTORY_SEPARATOR . $fileName;

            if ($result = file_exists($inlineFile)) {
                Yii::trace('Inline script at '.$position.' is already saved in '.$inlineFile);
            }
            else {
                Yii::trace('Saving inline script at '.$position.' into '.$inlineFile);
                $result = file_put_contents($inlineFile, $code);
            }

            if ($result) {
                $this->owner->registerScriptFile($inlineUrl, $position);
                $this->owner->clearInlineCode($position);
            }
        }
        else {
            Yii::trace('Inline script at '.$position.' is too small.');
        }

        $this->owner->stopCounters('saving-inline');
    }

}