<?php

class MergingBehavior extends YiissetBaseBehavior {
    
    /**
     * Следует ли объединять стили в один файл.
     * 
     * @var boolean
     */
    public $combineCssFiles = true;

    /**
     * Имя объединённого файла стилей.
     * 
     * @var string
     */
    public $cssFileName = 'style.css';

    /**
     * Следует ли объединять скрипты в один файл.
     * 
     * @var boolean
     */
    public $combineScriptFiles = true;

    /**
     * Имя объединённого файла скриптов.
     * 
     * @var string
     */
    public $scriptFileName = 'script.js';

    public function init()
    {
        if (!$this->combineCssFiles && !$this->combineScriptFiles) {
            return;
        }

        $this->owner->features[] = 'merging files';

        $this->owner->onBeforeOptimization->add(function(YiissetEvent $event) {
            if ($this->combineCssFiles && $event->type === 'css') {
                $this->mergeCssFiles();
            }

            if ($this->combineScriptFiles && $event->type === 'js') {
                $this->mergeScriptFiles($event->position);
            }
        });
    }

    /**
     * Get unique filename for combined files
     * 
     * @param string $name default filename
     * @param array $files files to be combined
     * @param string $type css media or script position
     * @return string unique filename
     */
    protected function getCombinedFileName($name, $files, $type = '')
    {
        $raw = '';
        foreach ($files as $file) {
            $raw .= "\0".$file.(file_exists($file) ? filemtime($file)."\0" : '');
        }
        $revision = $this->owner->getAssetVersion();
        $ext = ($type === '' ? '' : '-'.$type).'-'.$this->owner->hash($raw).($revision ? '-'.$revision : '');
        $pos = strrpos($name, '.');
        $name = $pos === false ? $name . $ext : substr_replace($name, $ext, $pos, 0);
        return strtr($name, '+=/ ', '--__');
    }

    /**
     * Объединить css-файлы в один файл.
     * Код  взят из yii-EClientScript и немного дополнен.
     */
    protected function mergeCssFiles()
    {
        // Check the need for combination
        if (count($this->owner->cssFiles) < 2) return;
        
        $this->owner->startCounters('combining combining-css');

        $cssFiles = array();
        foreach ($this->owner->cssFiles as $url => $media) {
            $file = $this->owner->getLocalPath($url);
            if ($file === false) {
                $cssFiles[$url] = $media;
            } else {
                // DO-NOT convert media to lower HERE (i.e: lt IE 6)
                $media = $media === '' ? 'all' : $media;
                if (!isset($cssFiles[$media])) {
                    $cssFiles[$media] = array();
                }
                $cssFiles[$media][$url] = $file;
            }
        }

        $this->owner->cssFiles = array();
        foreach ($cssFiles as $media => $files) {
            if ($media === 'all') {
                $media = '';
            }
            if (!is_array($files)) {
                $url = $media;
                $media = $files;
            } elseif (count($files) === 1) {
                $url = key($files);
            } else {
                // get unique combined filename
                $fname = $this->getCombinedFileName($this->owner->cssFileName, $files, $media);
                $fpath = Yii::app()->assetManager->basePath . DIRECTORY_SEPARATOR . $fname;
                // check exists file
                if (($valid = file_exists($fpath)) === true) {
                    foreach ($files as $file) {
                        if ($this->owner->isNewer($fpath, $file)) {
                            $valid = false;
                            break;
                        }
                    }
                }
                // re-generate the file
                if (!$valid) {
                    $urlRegex = '#url\s*\(\s*([\'"])?(?!/|http://|data\:)([^\'"\s])#i';
                    $fileBuffer = '';
                    $charsetLine = '';
                    foreach ($files as $url => $file) {
                        $contents = file_get_contents($file);
                        if ($contents) {
                            // Reset relative url() in css file
                            if (preg_match($urlRegex, $contents)) {
                                $reurl = $this->owner->getRelativeUrl(Yii::app()->assetManager->baseUrl, dirname($url));
                                $contents = preg_replace_callback($urlRegex, function($matches) use($reurl) {
                                    return 'url('.$matches[1].$reurl.'/'.$matches[2];
                                }, $contents);
                            }
                            // Check @charset line
                            if (preg_match('/@charset\s+"(.+?)";?/', $contents, $matches)) {
                                if ($charsetLine === '') {
                                    $charsetLine = '@charset "' . $matches[1] . '"' . ";\n";
                                }
                                $contents = preg_replace('/@charset\s+"(.+?)";?/', '', $contents);
                            }

                            // Append the contents to the fileBuffer
                            $fileBuffer .= $contents . "\n\n";
                        }
                    }
                    Yii::trace('Saving merged css into '.$fpath);
                    file_put_contents($fpath, $charsetLine . $fileBuffer);
                }
                else {
                    Yii::trace('Merged css is already saved in '.$fpath);
                }
                // real url of combined file
                $url = Yii::app()->assetManager->baseUrl . '/' . $fname;
            }
            $this->owner->cssFiles[$url] = $media;
        }

        $this->owner->stopCounters('combining combining-css');
    }

    /**
     * Объединить все скрипты в один файл.
     * Код взят из yii-EClientScript и немного дополнен.
     * 
     * @param  int $position
     */
    protected function mergeScriptFiles($position)
    {
        // Check the need for combination
        if (!isset($this->owner->scriptFiles[$position]) || count($this->owner->scriptFiles[$position]) < 2) return;
    
        $this->owner->startCounters('combining combining-js');

        $toCombine = array();
        $indexCombine = 0;
        $scriptName = $scriptValue = array();
        foreach ($this->owner->scriptFiles[$position] as $url => $value) {
            if (is_array($value) || !($file = $this->owner->getLocalPath($url))) {
                $scriptName[] = $url;
                $scriptValue[] = $value;
            } else {
                $toCombine[$url] = $file;
            }
        }
        if (count($toCombine) > 1) {
            // get unique combined filename
            $fname = $this->getCombinedFileName($this->owner->scriptFileName, array_values($toCombine), $position);
            $fpath = Yii::app()->assetManager->basePath . DIRECTORY_SEPARATOR . $fname;
            // check exists file
            if (($valid = file_exists($fpath)) === true) {
                foreach ($toCombine as $file) {
                    if ($this->owner->isNewer($fpath, $file)) {
                        $valid = false;
                        break;
                    }
                }
            }
            // re-generate the file
            if (!$valid) {
                $fileBuffer = '';
                foreach ($toCombine as $url => $file) {
                    $contents = file_get_contents($file);
                    if ($contents) {
                        $fileBuffer .= $contents . "\n;\n";
                    }
                }
                Yii::trace('Saving merged script into '.$fpath);
                file_put_contents($fpath, $fileBuffer);
            }
            else {
                Yii::trace('Merged script is already saved in '.$fpath);
            }
            // add the combined file into scriptFiles
            $url = Yii::app()->assetManager->baseUrl . '/' . $fname;
            $scriptName[] = $url;
            $scriptValue[] = $url;
        }
        // use new scriptFiles list replace old ones
        $this->owner->scriptFiles[$position] = array_combine($scriptName, $scriptValue);

        $this->owner->stopCounters('combining combining-js');
    }

}