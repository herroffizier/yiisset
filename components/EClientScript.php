<?php
/**
 * Yiisset Client Script
 *
 * Замена стаднартоного компонента clientScript с рядом улучшений и новых фич.
 * 
 * @author  Martin Stolz <herr.offizier@gmail.com>
 * @package ext.yiisset
 */

/**
 * Расщиренный CClientScript.
 * Лучше использовать совместно с входящим в расширение {@link EAssetManager}.
 */
class EClientScript extends CClientScript
{

    /**
     * Следует ли объединять стили в один файл.
     * 
     * @var boolean
     */
    public $combineCssFiles = true;

    /**
     * Следует ли оптимизировать css-файлы.
     * 
     * @var boolean
     */
    public $optimizeCssFiles = true;

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
     * Следует ли отпимизировать скрипты.
     * 
     * @var boolean
     */
    public $optimizeScriptFiles = true;

    /**
     * Имя объединённого файла скриптов.
     * 
     * @var string
     */
    public $scriptFileName = 'script.js';

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
    public $zopfliArgs = '-i20';

    /**
     * Путь к исполняемому файлу node.js.
     * Node.js используется для ряда фич - например, для компиляции CoffeeScript.
     * Если путь некорректен или равен false, фичи, которым он нужен, не будут
     * использоваться.
     * 
     * @var mixed
     */
    public $nodeExec = '/usr/local/bin/node';

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

    /**
     * Счётчики времени для различных действий.
     * 
     * @var array
     */
    protected $counters = array();

    /**
     * Время последнего запуска для каждого действия.
     * 
     * @var array
     */
    protected $lastStartedTime = array();

    /**
     * @var array local base path & url
     */
    private $_baseUrlMap = array();

    /**
     * @var string base request url
     */
    private $_baseUrl;

    /**
     * init base url map
     */
    public function init()
    {
        // request
        $this->_baseUrl = Yii::app()->assetManager->baseUrl;
        $baseUrl = $this->_baseUrl . '/';
        $this->_baseUrlMap[$baseUrl] = Yii::app()->assetManager->basePath . DIRECTORY_SEPARATOR;
        // themes
        if (Yii::app()->theme) {
            $baseUrl = Yii::app()->theme->baseUrl . '/';
            $this->_baseUrlMap[$baseUrl] = Yii::app()->theme->basePath . DIRECTORY_SEPARATOR;
        }

        parent::init();
    }

    /**
     * Получить идентификатор текущей ревизии ресурсов, если это возможно.
     * В проивном случае будет возвращена дата изменения указанного пути.
     * 
     * @param  string $path 
     * @return string
     */
    protected function getAssetVersion($path = null)
    {
        $mtime = null;
        if ($path && file_exists($path)) {
            $mtime = filemtime($path);
        }
        return 
            (Yii::app()->assetManager instanceof EAssetManager && Yii::app()->assetManager->assetVersion)
                ? Yii::app()->assetManager->assetVersion
                : $mtime;
    }

    /**
     * Посчитать хеш от строки.
     * Заимствован из CAssetManager.
     * 
     * @param  string $string
     * @return string
     */
    protected function hash($string)
    {
        return sprintf('%x',crc32($string));
    }

    /**
     * Get unique filename for combined files
     * 
     * @param string $name default filename
     * @param array $files files to be combined
     * @param string $type css media or script position
     * @return string unique filename
     */
    private function getCombinedFileName($name, $files, $type = '')
    {
        $raw = '';
        foreach ($files as $file) {
            $raw .= "\0".$file.(file_exists($file) ? filemtime($file)."\0" : '');
        }
        $revision = $this->getAssetVersion();
        $ext = ($type === '' ? '' : '-'.$type).'-'.$this->hash($raw).($revision ? '-'.$revision : '');
        $pos = strrpos($name, '.');
        $name = $pos === false ? $name . $ext : substr_replace($name, $ext, $pos, 0);
        return strtr($name, '+=/ ', '--__');
    }

    /**
     * Get realpath of published file via its url, refer to {link: CAssetManager}
     * 
     * @return string local file path for this script or css url
     */
    private function getLocalPath($url)
    {
        foreach ($this->_baseUrlMap as $baseUrl => $basePath) {
            if (!strncmp($url, $baseUrl, strlen($baseUrl))) {
                return $basePath . substr($url, strlen($baseUrl));
            }
        }
        return false;
    }

    /**
     * Calculate the relative url
     * 
     * @param  string $from source url, begin with slash and not end width slash.
     * @param  string $to dest url
     * @return string result relative url
     */
    private function getRelativeUrl($from, $to)
    {
        $relative = '';
        while (true) {
            if ($from === $to) {
                return $relative;
            } elseif ($from === dirname($from)) {
                return $relative . substr($to, 1);
            } elseif (!strncmp($from . '/', $to, strlen($from) + 1)) {
                return $relative . substr($to, strlen($from) + 1);
            }
            $from = dirname($from);
            $relative .= '../';
        }
    }

    /**
     * Проверить, является ли второй файл ($copy) новее первого ($source).
     * 
     * @param  string  $file
     * @param  string  $copy
     * @return boolean
     */
    protected function isNewer($file, $copy)
    {
        if (!file_exists($file) && file_exists($copy)) {
            Yii::trace(basename($file).' is missing but copy ('.basename($copy).') exists.');
            return true;
        }

        if (!file_exists($copy)) {
            Yii::trace(basename($file).' has no copy ('.basename($copy).').');
            return false;
        }

        $cMtime = filemtime($copy);
        $fMtime = filemtime($file);
        if ($cMtime >= $fMtime) {
            Yii::trace(basename($file).' copy ('.basename($copy).') is newer.');
        }
        else {
            Yii::trace(basename($file).' copy ('.basename($copy).') is out of date.');
        }
        return $cMtime >= $fMtime;
    }

    /**
     * Начать отсчёт времени по указанным в $names событиям.
     * Если событий несколько, их названия должны быть разделены
     * пробелами.
     * 
     * @param  string $names
     */
    protected function startCounters($names)
    {
        $names = explode(' ', $names);
        $time = microtime(true);
        foreach ($names as $name) {
            $this->lastStartedTime[$name] = $time;
        }
    }

    /**
     * Закончить отсчёт времени по указанным в $names событий.
     * Если событий несколько, их названия должны быть разделены
     * пробелами.
     * 
     * @param  string $names
     */
    protected function stopCounters($names)
    {
        $names = explode(' ', $names);
        $time = microtime(true);
        foreach ($names as $name) {
            $duration = microtime(true) - $this->lastStartedTime[$name];
            if (!isset($this->counters[$name])) {
                $this->counters[$name] = 0;
            }
            $this->counters[$name] += $duration;
        }
    }

    /**
     * Обработать файл $fromFile командой $command, результатом выполнения
     * которой должен стать $toFile.
     * 
     * Если $toFile не указан, то $fromFile будет предварительно перемещён
     * во временный файл и результатом выполнения команды будет $fromFile.
     * 
     * Для того, чтобы по нескольку раз один и тот же файл, создаётся $touchFile,
     * чья дата изменения дожна быть новее, чем у $fromFile.
     * Если указан $toFile, то в качестве $touchFile используется он.
     *
     * @throws CException если после выполнения команды результирующий файл не был создан
     *
     * @param  string                $tool              название команды, используется для лога
     * @param  string                $command           команда, может содержать метки #FROM_FILE# и #TO_FILE#
     * @param  string                $fromFile          исходный (и результирующий, если $toFile = null) файл
     * @param  string[optional]      $toFile            результирующий файл
     * @param  boolean[optional]     $removeSource      удалять ли исходный файл в случае успеха
     */
    protected function optimizeFile($tool, $command, $fromFile, $toFile = null, $removeSource = false) 
    {
        $tokenizedTool = preg_replace('/[\s\.]+/', '_', $tool);
        $touchFile = $toFile ?: $fromFile.'.processed.'.$tokenizedTool;

        if ($this->isNewer($fromFile, $touchFile)) return;

        $lockFile = $fromFile.'.lock';
        $lockFileHandle = fopen($lockFile, 'w+');
        flock($lockFileHandle, LOCK_EX);

        if (!$toFile) {
            $useTempFile = true;

            $toFile = $fromFile;
            $fromFile = $toFile.'.tmp';
            rename($toFile, $fromFile);
        }
        else {
            $useTempFile = false;
        }

        $command = 
            str_replace(
                array('#FROM_FILE#', '#TO_FILE#'), 
                array(escapeshellarg($fromFile), escapeshellarg($toFile)), 
                $command
            );
        $command = $command.' 2>&1';
        exec($command, $output);
        if ($output) {
            $output = implode("\n", $output);
            Yii::trace($tool.' ('.$command.') output: '.$output);
        }

        if (file_exists($toFile)) {
            Yii::trace(
                $tool.' saves '.number_format(filesize($fromFile) - filesize($toFile)).' bytes for '
                .pathinfo($useTempFile ? $toFile : $fromFile, PATHINFO_BASENAME).'.'
            );

            if ($useTempFile || (!$useTempFile && $removeSource)) {
                Yii::trace('Removing '.$fromFile.' after successful optimization.');
                unlink($fromFile);
            }
        }
        else {
            if ($useTempFile) {
                rename($fromFile, $toFile);
            }
            fclose($lockFileHandle);
            unlink($lockFile);

            throw new CException($tool.' failed to optimize '.($useTempFile ? $toFile : $fromFile).'.');
        }

        touch($touchFile);

        fclose($lockFileHandle);
        unlink($lockFile);
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
            $this->nodeExec.' '.escapeshellarg($this->coffeeScriptExec)
            .' -o '.escapeshellarg(dirname($file).'/')
            .' -c #FROM_FILE#';

        $compiledScriptFile = mb_substr($file, 0, mb_strlen($file) - 6).'js';

        $this->optimizeFile('CoffeeScript', $cmd, $file, $compiledScriptFile);
    }

    /**
     * Скомпилировать каждый .coffee файл в указанной позиции в JS.
     * 
     * @param  int $position
     */
    protected function compileCoffeeScriptFiles($position)
    {
        if (!$this->coffeeScriptExec || empty($this->scriptFiles[$position])) return;
        
        $scriptFiles = array();
        
        foreach ($this->scriptFiles[$position] as $url => $attributes) {
            if (
                !preg_match('/\.coffee$/', $url) 
                || !($path = $this->getLocalPath($url))
            ) {
                $scriptFiles[$url] = $attributes;
                continue;
            }

            $this->startCounters('coffee');

            $compiledScript = mb_substr($url, 0, mb_strlen($url) - 6).'js';
            $compiledScriptFile = mb_substr($path, 0, mb_strlen($path) - 6).'js';

            $this->compileCoffeeScriptFile($path);
            $scriptFiles[$compiledScript] = $attributes;

            $this->stopCounters('coffee');
        }

        $this->scriptFiles[$position] = $scriptFiles;
    }

    /**
     * Объединить css-файлы в один файл.
     * Код  взят из yii-EClientScript и немного дополнен.
     */
    protected function combineCssFiles()
    {
        // Check the need for combination
        if (count($this->cssFiles) < 2) return;
        
        $this->startCounters('combining combining-css');

        $cssFiles = array();
        foreach ($this->cssFiles as $url => $media) {
            $file = $this->getLocalPath($url);
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

        $this->cssFiles = array();
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
                $fname = $this->getCombinedFileName($this->cssFileName, $files, $media);
                $fpath = Yii::app()->assetManager->basePath . DIRECTORY_SEPARATOR . $fname;
                // check exists file
                if (($valid = file_exists($fpath)) === true) {
                    foreach ($files as $file) {
                        if ($this->isNewer($fpath, $file)) {
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
                                $reurl = $this->getRelativeUrl(Yii::app()->assetManager->baseUrl, dirname($url));
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
            $this->cssFiles[$url] = $media;
        }

        $this->stopCounters('combining combining-css');
    }

    /**
     * Получить строку со всеми инлайновыми скриптами в указанной позиции.
     * Скрипты для позиций POS_READY and POS_LOAD will будут обёрнуты в функции
     * $(function() { ... }) и $(window).on('load', function() { ... }) соответственно.
     * 
     * @param  int $position
     * @return string
     */
    protected function getInlineCode($position)
    {
        if (empty($this->scripts[$position])) return '';

        $code = implode("\n", $this->scripts[$position]);
        switch ($position) {
            case self::POS_READY:
                $code = 'jQuery(function($){'.$code.'});';
                break;

            case self::POS_LOAD:
                $code = 'jQuery(window).on(\'load\',function(){'.$code.'});';
                break;
        }

        return $code;
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
        $isEndPos = $position === self::POS_END;
        if (
            empty($this->scripts[$position])
            && (
                !$isEndPos
                || (empty($this->scripts[self::POS_READY]) && empty($this->scripts[self::POS_LOAD])) 
            )
        ) {
            return;
        }

        $this->startCounters('saving-inline');

        $code = $this->getInlineCode($position);

        if ($isEndPos) {
            $code = array_filter(array(
                $code, 
                $this->getInlineCode(self::POS_READY), 
                $this->getInlineCode(self::POS_LOAD),
            )); 
            $code = implode("\n", $code);
        }

        $fileName = 'inline-' . $this->hash($code) . '.js';
        $inlineFile = Yii::app()->assetManager->basePath . DIRECTORY_SEPARATOR . $fileName;
        $inlineUrl = Yii::app()->assetManager->baseUrl . DIRECTORY_SEPARATOR . $fileName;

        if ($result = file_exists($inlineFile)) {
            Yii::trace('Inline script is already saved in '.$inlineFile);
        }
        else {
            Yii::trace('Saving inline script into '.$inlineFile);
            $result = file_put_contents($inlineFile, $code);
        }

        if ($result) {
            $this->registerScriptFile($inlineUrl, $position);

            unset($this->scripts[$position]);
            if ($isEndPos) {
                unset($this->scripts[self::POS_READY]);
                unset($this->scripts[self::POS_LOAD]);
            }
        }

        $this->stopCounters('saving-inline');
    }

    /**
     * Объединить все скрипты в один файл.
     * Код взят из yii-EClientScript и немного дополнен.
     * 
     * @param  int $position
     */
    protected function combineScriptFiles($position = self::POS_HEAD)
    {
        // Check the need for combination
        if (!isset($this->scriptFiles[$position]) || count($this->scriptFiles[$position]) < 2) return;
    
        $this->startCounters('combining combining-js');

        $toCombine = array();
        $indexCombine = 0;
        $scriptName = $scriptValue = array();
        foreach ($this->scriptFiles[$position] as $url => $value) {
            if (is_array($value) || !($file = $this->getLocalPath($url))) {
                $scriptName[] = $url;
                $scriptValue[] = $value;
            } else {
                $toCombine[$url] = $file;
            }
        }
        if (count($toCombine) > 1) {
            // get unique combined filename
            $fname = $this->getCombinedFileName($this->scriptFileName, array_values($toCombine), $position);
            $fpath = Yii::app()->assetManager->basePath . DIRECTORY_SEPARATOR . $fname;
            // check exists file
            if (($valid = file_exists($fpath)) === true) {
                foreach ($toCombine as $file) {
                    if ($this->isNewer($fpath, $file)) {
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
        $this->scriptFiles[$position] = array_combine($scriptName, $scriptValue);

        $this->stopCounters('combining combining-js');
    }

    /**
     * Сжать скрипт при помощи Uglify.js.
     * 
     * @param  string $file
     */
    protected function uglifyFile($file)
    {
        if (!$this->uglifyjsExec) return;

        $cmd = $this->nodeExec.' '.escapeshellarg($this->uglifyjsExec).' #FROM_FILE#'.($this->uglifyjsArgs ? ' '.$this->uglifyjsArgs : '').' -o #TO_FILE#';
        $this->optimizeFile('Uglify.js', $cmd, $file);
    }

    /**
     * Сжать все скрипты в указанной позиции.
     * 
     * @param  string $position
     */
    public function uglifyScriptFiles($position)
    {
        if (empty($this->scriptFiles[$position])) return;

        $this->startCounters('optimizing optimizing-js');

        foreach ($this->scriptFiles[$position] as $url => $attributes) {
            if (!($path = $this->getLocalPath($url))) continue;
            $this->uglifyFile($path);
        }

        $this->stopCounters('optimizing optimizing-js');
    }

    /**
     * Сжать файл стилей при помощи clean-css.
     * 
     * @param  string $file
     */
    protected function cleancssFile($file)
    {
        if (!$this->cleancssExec) return;

        $cmd = $this->nodeExec.' '.escapeshellarg($this->cleancssExec).($this->cleancssArgs ? ' '.$this->cleancssArgs : '').' -o #TO_FILE# #FROM_FILE#';
        $this->optimizeFile('clean-css', $cmd, $file);
    }

    /**
     * Сжать все стили при помощи clean-css.
     */
    protected function cleancssFiles()
    {
        if (empty($this->cssFiles)) return;

        $this->startCounters('optimizing optimizing-css');

        foreach ($this->cssFiles as $url => $media) {
            if (!($path = $this->getLocalPath($url))) continue;
            $this->cleancssFile($path);
        }

        $this->stopCounters('optimizing optimizing-css');
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

        $basePath = Yii::app()->assetManager->publish(__DIR__.'/../assets');
        $this->registerScriptFile($basePath.'/'.(YII_DEBUG ? 'lazyload.js' : 'lazyload.min.js'), $position);

    }

    /**
     * Загружать все стили при помощи LazyLoad.
     */
    protected function lazyLoadCssFiles()
    {
        if (empty($this->cssFiles) || count($this->cssFiles) < 2) return;

        $this->startCounters('lazyload lazyload-css');

        $cssFiles = array();
        foreach ($this->cssFiles as $url => $media) {
            if ($media) {
                $cssFiles[] = array('url' => $url, 'media' => $media);
            }
            else {
                $cssFiles[] = $url;
            }
        }

        $code = 'LazyLoad.css('.CJSON::encode($cssFiles).');';
        $this->registerScript('lazy_load_css', $code, self::POS_HEAD);

        $this->cssFiles = array();

        $this->registerLazyLoad(self::POS_HEAD);

        $this->stopCounters('lazyload lazyload-css');
    }

    /**
     * Загружать все скрипты в указанной позиции через LazyLoad.
     * 
     * @param  int $position
     */
    protected function lazyLoadScriptFiles($position)
    {
        if (!isset($this->scriptFiles[$position]) || count($this->scriptFiles[$position]) < 2) return;

        $this->startCounters('lazyload lazyload-js');

        $scriptFiles = array();
        foreach($this->scriptFiles[$position] as $url => $attributes) {
            if (is_array($attributes)) {
                $scriptFiles[] = array_merge($attributes, compact('url'));
            }
            else {
                $scriptFiles[] = $url;
            }
        }

        $code = 'LazyLoad.js('.CJSON::encode($scriptFiles).');';
        $this->registerScript('lazyLoad_scripts_'.$position, $code, $position);

        $this->scriptFiles[$position] = array();

        $this->registerLazyLoad($position);

        $this->stopCounters('lazyload lazyload-js');
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
        
        $this->optimizeFile($tool, $cmd, $file, $gzippedFile);
    }

    /**
     * Создать сжатые gzip'ом копии перечисленных файлов.
     *  
     * @param  array $files
     */
    protected function createGzippedCopies(array $files)
    {
        foreach ($files as $url => $attributes) {
            if (!($path = $this->getLocalPath($url))) continue;
            $this->gzipFile($path);
        }
    }

    /**
     * Создать сжатые копии всех стилей.
     */
    protected function createGzippedCssFiles()
    {
        if (empty($this->cssFiles)) return;

        $this->startCounters('compressing compressing-css');
        $this->createGzippedCopies($this->cssFiles);
        $this->stopCounters('compressing compressing-css');
    }

    /**
     * Создать сжатые копии всех скриптов в указанной позиции.
     * 
     * @param  int $position
     */
    protected function createGzippedScriptFiles($position)
    {
        if (empty($this->scriptFiles[$position])) return;

        $this->startCounters('compressing compressing-js');
        $this->createGzippedCopies($this->scriptFiles[$position]);
        $this->stopCounters('compressing compressing-js');
    }

    /**
     * Код метода CClientScript::render() дополнен подсчётом статистики
     * и выводом отладочной информации в лог.
     * 
     * @param  string $output
     */
    public function render(&$output)
    {
        $this->startCounters('total');

        // check paths to executables and build features list
        $features = array();

        if (!$this->nodeExec || !file_exists($this->nodeExec)) {
            Yii::trace('No node.js executable found, disabling CoffeeScript and UglifyJS');
            $this->nodeExec = false;
            $this->coffeeScriptExec = false;
            $this->uglifyjsExec = false;
            $this->cleancssExec = false;
        }

        if ($this->coffeeScriptExec && !file_exists($this->coffeeScriptExec)) {
            Yii::trace('No CoffeeScript executable found, disabling CoffeeScript compilation');
            $this->coffeeScriptExec = false;
        }

        if ($this->uglifyjsExec && !file_exists($this->uglifyjsExec)) {
            Yii::trace('No UglifyJS executable found, disabling UglifyJS optimization');
            $this->uglifyjsExec = false;
        }

        if ($this->cleancssExec && !file_exists($this->cleancssExec)) {
            Yii::trace('No clean-css executable found, disabling clean-css optimization');
            $this->cleancssExec = false;
        }

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

        $features = array_keys(array_filter(array(
            'coffeescript'          => $this->coffeeScriptExec,
            'uglifyjs'              => $this->uglifyjsExec && $this->optimizeScriptFiles,
            'cleancss'              => $this->cleancssExec && $this->optimizeCssFiles,
            'gzip precompress'      => $this->saveGzippedCopy && $this->gzipExec && !$this->zopfliExec,
            'zopfli precompress'    => $this->saveGzippedCopy && $this->zopfliExec,
            'combining js'          => $this->combineScriptFiles,
            'combining css'         => $this->combineCssFiles,
            'saving inline js'      => $this->disableInlineScripts,
            'lazyload'              => $this->useLazyLoad,
        )));
        
        if ($features) {
            Yii::trace('Yiisset started with '.implode(', ', $features)).'.';
        }
        else {
            Yii::trace('Yiisset satarted.');
        }

        parent::render($output);

        $this->stopCounters('total');

        arsort($this->counters);
        $statistics = array();
        foreach ($this->counters as $type => $time) {
            $statistics[] = str_pad($type.':', 16)."\t\t".number_format($time, 4).'s';
        }

        Yii::trace("Yiisset completed.\nStatistics (slowest first):\n".implode("\n", $statistics));
        
    }

    /**
     * В метод CClientScript::renderCoreScripts добавлена поддержка 
     * аттрибута media для css.
     * 
     * Теперь в описании package наравне с прежним синтаксисом можно исспользовать
     * такую форму записи:
     *         'css' => array(
     *             'style1.css',
     *             'style2.css',
     *             array('print.css', 'media' => 'print'),
     *         ),
     * 
     * @see  https://github.com/yiisoft/yii/issues/942
     */
    public function renderCoreScripts()
    {
        if($this->coreScripts===null)
            return;
        $cssFiles=array();
        $jsFiles=array();
        foreach($this->coreScripts as $name=>$package)
        {
            $baseUrl=$this->getPackageBaseUrl($name);
            if(!empty($package['js']))
            {
                foreach($package['js'] as $js)
                    $jsFiles[$baseUrl.'/'.$js]=$baseUrl.'/'.$js;
            }
            if(!empty($package['css']))
            {
                foreach($package['css'] as $css) {
                    // add support for media types for css in packages
                    if (is_array($css)) {
                        $file = array_shift($css);
                        $cssFiles[$baseUrl.'/'.$file]=isset($css['media']) ? $css['media'] : '';
                    }
                    else {
                        $cssFiles[$baseUrl.'/'.$css]='';
                    }
                }
            }
        }
        // merge in place
        if($cssFiles!==array())
        {
            foreach($this->cssFiles as $cssFile=>$media)
                $cssFiles[$cssFile]=$media;
            $this->cssFiles=$cssFiles;
        }
        if($jsFiles!==array())
        {
            if(isset($this->scriptFiles[$this->coreScriptPosition]))
            {
                foreach($this->scriptFiles[$this->coreScriptPosition] as $url => $value)
                    $jsFiles[$url]=$value;
            }
            $this->scriptFiles[$this->coreScriptPosition]=$jsFiles;
        }
    }

    /**
     * Combine css files and script files before renderHead.
     * 
     * @param string the output to be inserted with scripts.
     */
    public function renderHead(&$output)
    {
        if ($this->combineCssFiles) {
            $this->combineCssFiles();
        }

        if ($this->optimizeCssFiles) {
            $this->cleancssFiles();
        }

        // создание сжатых копий стилей должно идти перед вызовом
        // lazyLoadCssFiles
        if ($this->saveGzippedCopy) {
            $this->createGzippedCssFiles();
        }

        if ($this->enableJavaScript) {
            $this->compileCoffeeScriptFiles(self::POS_HEAD);

            if ($this->disableInlineScripts) {
                $this->saveInlineCodeToFile(self::POS_HEAD);
            }

            if ($this->combineScriptFiles) {
                $this->combineScriptFiles(self::POS_HEAD);
            }

            if ($this->optimizeScriptFiles) {
                $this->uglifyScriptFiles(self::POS_HEAD);
            }

            if ($this->useLazyLoad) {
                // очерёдность вызовов важна: если lazyLoadCssFiles будет
                // выполнен перед lazyLoadScriptFiles, подключение LazyLoad 
                // будет удалено.
                $this->lazyLoadScriptFiles(self::POS_HEAD);
                $this->lazyLoadCssFiles();
            }
        }

        if ($this->saveGzippedCopy) {
            $this->createGzippedScriptFiles(self::POS_HEAD);
        }

        parent::renderHead($output);
    }

    /**
     * Inserts the scripts at the beginning of the body section.
     * 
     * @param string the output to be inserted with scripts.
     */
    public function renderBodyBegin(&$output)
    {
        // $this->enableJavascript has been checked in parent::render()
        $this->compileCoffeeScriptFiles(self::POS_BEGIN);

        if ($this->disableInlineScripts) {
            $this->saveInlineCodeToFile(self::POS_BEGIN);
        }

        if ($this->combineScriptFiles) {
            $this->combineScriptFiles(self::POS_BEGIN);
        }

        if ($this->optimizeScriptFiles) {
            $this->uglifyScriptFiles(self::POS_BEGIN);
        }

        if ($this->useLazyLoad) {
            $this->lazyLoadScriptFiles(self::POS_BEGIN);
        }

        if ($this->saveGzippedCopy) {
            $this->createGzippedScriptFiles(self::POS_BEGIN);
        }

        parent::renderBodyBegin($output);
    }

    /**
     * Inserts the scripts at the end of the body section.
     * 
     * @param string the output to be inserted with scripts.
     */
    public function renderBodyEnd(&$output)
    {
        // $this->enableJavascript has been checked in parent::render()
        $this->compileCoffeeScriptFiles(self::POS_END);
        
        if ($this->disableInlineScripts) {
            $this->saveInlineCodeToFile(self::POS_END);
        }

        if ($this->combineScriptFiles) {
            $this->combineScriptFiles(self::POS_END);
        }

        if ($this->optimizeScriptFiles) {
            $this->uglifyScriptFiles(self::POS_END);
        }

        if ($this->useLazyLoad) {
            $this->lazyLoadScriptFiles(self::POS_END);
        }

        if ($this->saveGzippedCopy) {
            $this->createGzippedScriptFiles(self::POS_END);
        }

        parent::renderBodyEnd($output);
    }
}
