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
    const MODE_NORMAL   = 1;
    const MODE_DELAY    = 2;
    const MODE_RESTORE  = 3;
    const MODE_SAVE     = 4;

    public $scripts = array();
    public $cssFiles = array();
    public $scriptFiles = array();

    /**
     * Набор поведений
     * @var array
     */
    public $behaviors = array(
        'CoffeeScriptBehavior',
        'InlineScriptBehavior',
        'MergingBehavior',
        'NodeJsBehavior',
        'CleancssBehavior',
        'UglifyJsBehavior',
        'GzipBehavior',
        'LazyLoadBehavior',

        'JsDelivrBehavior',
    );

    /**
     * Набор доступных фич.
     * Заполняется, как правило, поведениями.
     * 
     * @var array
     */
    public $features = array();

    /**
     * Отложенная обработка файлов.
     * Это экспериментальная фича и работает только в связке с Yiiq.
     *
     * Для того, чтобы выполнять обработку файлов в фоновом режиме,
     * необходимо соблюсти ряд требований:
     *     - У assetManager должен быть указан номер ревизии (assetVersion);
     *     - Yiiq должен быть запущен под тем же пользователем, что и веб-сервер;
     *     - Для консольного приложения должен быть указан путь к webroot;
     *     - Для консольного приложения должны быть доступны компоненты assetManager и clientScript;
     *     - Для консольного приложения у assetManager должны быть указаны basePath и baseUrl.
     *
     * @see  https://github.com/herroffizier/yiiq
     * @var boolean
     */
    public $delayed = false;

    /**
     * Очередь в Yiiq для заданий удалённой обработки.
     * Если null - используется стандартная очередь.
     * 
     * @var string
     */
    public $delayedQueue = null;

    /**
     * Проверять изменения файлов по CRC вместо даты изменения.
     * Полезно использовать при отладке, когда EAssetManager::forceCopy = true.
     * 
     * @var boolean
     */
    public $compareFilesByCrc = false;

    /**
     * Имя компонента YiiCacheMutex в системе.
     * Компонент используется для обработки файлов с использованием мьютексов.
     * Если false или компонент с указанным именем недоступен, мьютексы
     * не будут использоваться, вместо них будет использован flock.
     *
     * @see https://github.com/herroffizier/yiicachemutex
     * @var string
     */
    public $cacheMutexName = 'cacheMutex';

    /**
     * Сcылка на компонент YiiCacheMutex.
     * 
     * @var YiiCacheMutex
     */
    protected $cacheMutex = null;

    /**
     * Режим работы.
     * MODE_NORMAL - обычный режим, все остальные - для
     * отложенной обработки файлов.
     * 
     * @var int
     */
    protected $mode = self::MODE_NORMAL;

    /**
     * Хеш состояния для отложенной обработки.
     * Считается один раз при вызове getStateHash.
     * Метод должен вызываться внутри render.
     * 
     * @var string
     */
    protected $stateHash = null;

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
     * Массив скриптов, подключаемых через cdn.
     * 
     * @var array
     */
    protected $cdn = array();

    /**
     * @var array local base path & url
     */
    private $_baseUrlMap = array();

    /**
     * @var string base request url
     */
    private $_baseUrl;

    public function __construct()
    {
        Yii::setPathOfAlias('yiisset', __DIR__.'/..');
        Yii::import('yiisset.base.*');
        Yii::import('yiisset.plugins.compilers.*');
        Yii::import('yiisset.plugins.minifiers.*');
        Yii::import('yiisset.plugins.util.*');
        Yii::import('yiisset.plugins.cdn.*');

        // Подключаем поведения до вызова init, чтобы добиться
        // корректного задания свойств
        foreach ($this->behaviors as $index => $behavior) {
            if (is_array($behavior)) {
                $this->attachBehavior($index, $behavior);
            }
            else {
                $this->attachBehavior($behavior, array(
                    'class' => $behavior,
                ));
            }
        }
    }

    protected function raiseCustomEvent($eventName, $type, $position)
    {
        // События для поведений вызываются только в нормальном режиме и режиме
        // записи.
        if (!in_array($this->mode, [self::MODE_NORMAL, self::MODE_SAVE])) return;
        
        $event = new YiissetEvent($this, $type, $position);
        $this->raiseEvent($eventName, $event);
    }

    public function onBeforeOptimization($type, $position)
    {
        $this->raiseCustomEvent('onBeforeOptimization', $type, $position);
    }

    public function onOptimization($type, $position)
    {
        $this->raiseCustomEvent('onOptimization', $type, $position);
    }

    public function onAfterOptimization($type, $position)
    {
        $this->raiseCustomEvent('onAfterOptimization', $type, $position);
    }

    public function onInit()
    {
        $this->raiseEvent('onInit', new CEvent($this));
    }

    /**
     * init base url map
     */
    public function init()
    {
        // Если мы работаем внутри консольного приложения (Yiiq), добавляем недостающие методы приложению
        // и выставляем соответствующий режим работы.
        if (Yii::app() instanceof CConsoleApplication) {
            $this->mode = self::MODE_SAVE;
            Yii::app()->attachBehavior(
                'YiissetConsoleBehavior', 
                ['class' => 'yiisset.util.YiissetConsoleBehavior']
            );
        }

        // request
        $this->_baseUrl = Yii::app()->assetManager->baseUrl;
        $baseUrl = $this->_baseUrl . '/';
        $this->_baseUrlMap[$baseUrl] = Yii::app()->assetManager->basePath . DIRECTORY_SEPARATOR;
        // themes
        if (Yii::app()->hasComponent('theme')) {
            $baseUrl = Yii::app()->theme->baseUrl . '/';
            $this->_baseUrlMap[$baseUrl] = Yii::app()->theme->basePath . DIRECTORY_SEPARATOR;
        }

        $behaviors = $this->behaviors;
        $this->behaviors = array();
        parent::init();
        $this->behaviors = $behaviors;

        $this->onInit();
    }

    /**
     * Получить идентификатор текущей ревизии ресурсов, если это возможно.
     * В проивном случае будет возвращена дата изменения указанного пути.
     * 
     * @param  string $path 
     * @return string
     */
    public function getAssetVersion($path = null)
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
    public function hash($string)
    {
        return sprintf('%x',crc32($string));
    }

    /**
     * Получить хеш состояния.
     * Состоит из hasScripts, coreScripts, cdn, scripts, scriptFiles и cssFiles.
     * Должен считаться только внутри render.
     * 
     * @return string
     */
    protected function getStateHash()
    {
        if ($this->stateHash === null) {
            $this->stateHash = md5(CJSON::encode($this->getState(true))).'@'.$this->getAssetVersion();
        }

        return $this->stateHash;
    }

    /**
     * Получить имя файла состояния.
     * 
     * @return string
     */
    protected function getStateFilename()
    {
        return Yii::app()->assetManager->basePath.DIRECTORY_SEPARATOR.'state-'.$this->getStateHash().'.json';
    }

    /**
     * Проверить, было ли сохранено текущее состояние
     * ранее.
     * 
     * @return boolean
     */
    protected function isStateSaved()
    {
        return file_exists($this->getStateFilename());
    }

    /**
     * Получить текущее состояние.
     * 
     * Флаг $full должен быть true, если метод вызывается после
     * renderCoreScripts. В таком случае предполагается,
     * что все файлы в coreScripts и cdn были помещены в соответствующие
     * массивы scripts, scriptFiles и cssFiles.
     * 
     * @param  bool $full
     * @return array
     */
    protected function getState($full)
    {
        return [
            'hasScripts'    => $this->hasScripts,
            'coreScripts'   => $full ? $this->coreScripts : null,
            'cdn'           => $full ? $this->cdn : [],
            'scripts'       => $this->scripts,
            'scriptFiles'   => $this->scriptFiles,
            'cssFiles'      => $this->cssFiles,
        ];
    }

    /**
     * Сохранить текущее состояние в файл.
     * Метод должен вызываться после parent::render.
     */
    public function saveState()
    {
        return file_put_contents(
            $this->getStateFilename(),
            CJSON::encode($this->getState(false))
        );
    }

    /**
     * Восстановить состояние.
     * 
     * Если указан $hash, он заменяет собой рассчитанный
     * ранее. Это используется в YiissetJob.
     * 
     * @param  array $state
     * @param  string[optional] $hash
     */
    public function restoreState($state, $hash = null)
    {
        $this->hasScripts   = $state['hasScripts'];
        $this->coreScripts  = $state['coreScripts'];
        $this->cdn          = $state['cdn'];
        $this->scripts      = $state['scripts'];
        $this->scriptFiles  = $state['scriptFiles'];
        $this->cssFiles     = $state['cssFiles'];

        if ($hash) $this->stateHash = $hash;
    }

    /**
     * Восстановить состояние из файла.
     */
    public function restoreStateFromFile()
    {
        $state = file_get_contents($this->getStateFilename());
        $state = CJSON::decode($state);
        $this->restoreState($state);
    }

    /**
     * Get realpath of published file via its url, refer to {link: CAssetManager}
     * 
     * @return string local file path for this script or css url
     */
    public function getLocalPath($url)
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
    public function getRelativeUrl($from, $to)
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
    public function isNewer($file, $copy)
    {
        if (!file_exists($file) && file_exists($copy)) {
            Yii::trace(basename($file).' is missing but copy ('.basename($copy).') exists.');
            return true;
        }

        if (!file_exists($copy)) {
            Yii::trace(basename($file).' has no copy ('.basename($copy).').');
            return false;
        }

        $crcFile = $file.'.checksum';
        $currentCrc = null;
        if ($this->compareFilesByCrc && file_exists($crcFile)) {
            $currentCrc = (string)crc32(file_get_contents($file));

            $isNewer = file_get_contents($crcFile) === $currentCrc;
        }
        else {
            $cMtime = filemtime($copy);
            $fMtime = filemtime($file);
            $isNewer = $cMtime >= $fMtime;
        }

        if ($this->compareFilesByCrc && (!file_exists($crcFile) || !$isNewer)) {
            file_put_contents($crcFile, $currentCrc);
        }

        if ($isNewer) {
            Yii::trace(basename($file).' copy ('.basename($copy).') is actual'.($currentCrc !== null ? ' (by CRC)' : '').'.');
        }
        else {
            Yii::trace(basename($file).' copy ('.basename($copy).') is out of date'.($currentCrc !== null ? ' (by CRC)' : '').'.');
        }

        return $isNewer;
    }

    /**
     * Начать отсчёт времени по указанным в $names событиям.
     * Если событий несколько, их названия должны быть разделены
     * пробелами.
     * 
     * @param  string $names
     */
    public function startCounters($names)
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
    public function stopCounters($names)
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

    protected function lock($file)
    {
        if ($this->cacheMutex) {
            $handle = __CLASS__.':'.$file;
            $this->cacheMutex->acquire($handle);
        }
        else {
            $lockFile = $file.'.lock';
            $handle = fopen($lockFile, 'w+');
            flock($handle, LOCK_EX);
            $handle = array(
                'handle' => $handle,
                'file' => $lockFile,
            );
        }

        return $handle;
    }

    protected function unlock($handle)
    {
        if ($this->cacheMutex) {
            $this->cacheMutex->release($handle);
        }
        else {
            fclose($handle['handle']);
            if (file_exists($handle['file'])) unlink($handle['file']);
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
    public function processFile($tool, $command, $fromFile, $toFile = null, $removeSource = false) 
    {
        $tokenizedTool = preg_replace('/[\s\.]+/', '_', $tool);
        $touchFile = $toFile ?: $fromFile.'.processed.'.$tokenizedTool;

        if ($this->isNewer($fromFile, $touchFile)) return;

        $lock = $this->lock($fromFile);

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
            $oldSize = filesize($fromFile);
            $newSize = filesize($toFile);
            $sizeDiff = abs($oldSize - $newSize);

            if ($oldSize > $newSize) {
                Yii::trace(
                    $tool.' reduces '.pathinfo($useTempFile ? $toFile : $fromFile, PATHINFO_BASENAME).' size by '.number_format($sizeDiff).' bytes.'
                );
            }
            else {
                Yii::trace(
                    $tool.' enlarges '.pathinfo($useTempFile ? $toFile : $fromFile, PATHINFO_BASENAME).' size by '.number_format($sizeDiff).' bytes.'
                );
            }
            
            if ($useTempFile || (!$useTempFile && $removeSource)) {
                Yii::trace('Removing '.$fromFile.' after successful processing.');
                unlink($fromFile);
            }
        }
        else {
            if ($useTempFile) {
                rename($fromFile, $toFile);
            }
            $this->unlock($lock);

            throw new CException($tool.' failed to process '.($useTempFile ? $toFile : $fromFile).'.');
        }

        touch($touchFile);

        $this->unlock($lock);
    }

    /**
     * Получить строку со всеми инлайновыми скриптами в указанной позиции.
     * Для позиции CClientScript::POS_END будут возвращены так же скрипты в 
     * CClientScript::POS_READY и CClientScript::POS_LOAD.
     * Скрипты для позиций POS_READY and POS_LOAD will будут обёрнуты в функции
     * $(function() { ... }) и $(window).on('load', function() { ... }) соответственно.
     * 
     * @param  int $position
     * @return string
     */
    public function getInlineCode($position)
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

        if (isset($this->scripts[$position])) {
            $code = implode("\n", $this->scripts[$position]);
        }
        else {
            $code = '';
        }
        switch ($position) {
            case self::POS_READY:
                $code = 'jQuery(function($){'.$code.'});';
                break;

            case self::POS_LOAD:
                $code = 'jQuery(window).on(\'load\',function(){'.$code.'});';
                break;
        }

        if ($isEndPos) {
            $code = array_filter(array(
                $code, 
                $this->getInlineCode(self::POS_READY), 
                $this->getInlineCode(self::POS_LOAD),
            )); 
            $code = implode("\n", $code);
        }

        return $code;
    }

    /**
     * Удалить инлайновые скрипты в указанной позиции.
     * Для CClientScript::POS_END удаляются так же и скрипты в 
     * CClientScript::POS_READY и CClientScript::POS_LOAD.
     * 
     * @param  int $position
     */
    public function clearInlineCode($position)
    {
        $isEndPos = $position === self::POS_END;
        unset($this->scripts[$position]);
        if ($isEndPos) {
            unset($this->scripts[self::POS_READY]);
            unset($this->scripts[self::POS_LOAD]);
        }
    }

    /**
     * Выполнить обработку файлов при взведённом флаге
     * отложенной обработки.
     * 
     * @param  string &$output
     */
    protected function renderDelayed(&$output)
    {
        $hash = $this->getStateHash();

        if ($this->mode === self::MODE_NORMAL) {
            $this->features[] = 'delayed processing';

            if ($this->isStateSaved()) {
                // Состояние сохранено, восстанавливаемся.
                $this->restoreStateFromFile();
                $this->mode = self::MODE_RESTORE;

                Yii::trace('Yiisset restored state '.$hash.'.');
            }
            else {
                // Сохранённого состояния нет, нужно поставить задание на
                // отложенную обработку после parent::render.
                $this->mode = self::MODE_DELAY;

                Yii::trace('Yiisset delays processing assets for state '.$hash.'.');
            }
        }

        parent::render($output);

        switch ($this->mode) {
            case self::MODE_DELAY:
                Yii::app()->yiiq->enqueueJob(
                    'YiissetDelayedJob', 
                    ['state' => $this->getState(true), 'hash' => $hash], 
                    $this->delayedQueue ?: Yiiq::DEFAULT_QUEUE, 
                    'yiiq-'.$hash
                );
                break;

            case self::MODE_SAVE:
                $this->saveState();
                break;
        }
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

        if ($this->cacheMutexName && Yii::app()->hasComponent($this->cacheMutexName)) {
            $this->cacheMutex = Yii::app()->getComponent($this->cacheMutexName);
            $this->features[] = 'mutex file processing';
        }
        else {
            $this->cacheMutexName = false;
        }

        // Если выставлен флаг отложенной обработки, но
        // не указана ревизия файлов или недоступен планировщик
        // заданий Yiiq, снимаем флаг.
        if (
            $this->delayed 
            && (
                !$this->getAssetVersion() 
                || !Yii::app()->hasComponent('yiiq')
            )
        ) {
            $this->delayed = false;
        }

        if ($this->features) {
            Yii::trace('Yiisset started with '.implode(', ', $this->features)).'.';
        }
        else {
            Yii::trace('Yiisset started.');
        }

        if ($this->delayed) {
            $this->renderDelayed($output);
        }
        else {
            parent::render($output);
        }

        $this->stopCounters('total');

        arsort($this->counters);
        $statistics = array();
        foreach ($this->counters as $type => $time) {
            $statistics[] = str_pad($type.':', 16)."\t\t".number_format($time, 4).'s';
        }

        Yii::trace("Yiisset completed.\nStatistics (slowest first):\n".implode("\n", $statistics));    
    }

    /**
     * В метод CClientScript::registerCoreScript добавлена поддержка CDN.
     * 
     * Для того, чтобы подключить библиотеку через CDN, необходимо подключить
     * package вида
     *     [type]:[cdn]:[name]
     * или, если подключается js библиотека,
     *     [cdn]:[name]
     * где
     *     type - css или js (по умолчанию - js),
     *     cdn  - название CDN-сервиса,
     *     name - название библиотеки.
     *
     * В названии библиотеки можно указать желаемый номер версии через символ "@", например,
     * jsdelivr:jquery@1.5.2 подключит jQuery версии 1.5.2.
     * 
     * Поддержка различных CDN-сервисов реализуется через поведения.
     * 
     * @param  string $name
     * @return EClientScript
     */
    public function registerCoreScript($name)
    {
        $matches = null;
        if (!preg_match('/([^:]+):([^:]+)(:(.+)|)/', $name, $matches)) {
            return parent::registerCoreScript($name);
        }

        if (isset($matches[4])) {
            $type = $matches[1];
            $cdn = $matches[2];
            $script = $matches[4];
        }
        else {
            $type = 'js';
            $cdn = $matches[1];
            $script = $matches[2];
        }

        if (!isset($this->cdn[$type])) {
            $this->cdn[$type] = array();
        }

        if (!isset($this->cdn[$type][$cdn])) {
            $this->cdn[$type][$cdn] = array();
        }

        $this->cdn[$type][$cdn][] = $script;
        $this->hasScripts=true;

        return $this;
    }

    /**
     * Вернуть ссылки на CDN для файлов указанного типа (css или js).
     * 
     * @param  string $type
     * @return array
     */
    protected function renderCdnFiles($type)
    {
        if (!isset($this->cdn[$type])) return array();
        $files = array();
        foreach ($this->cdn[$type] as $cdn => $scripts) {
            $method = 'render'.$cdn.'Files';
            $files = array_merge($files, $this->$method($type, $scripts));
        }

        return array_combine($files, $files);
    }
   
    /**
     * В метод CClientScript::renderCoreScripts добавлена поддержка 
     * аттрибута media для css.
     * 
     * Теперь в описании package наравне с прежним синтаксисом можно исспользовать
     * такую форму записи:
     *     'css' => array(
     *         'style1.css',
     *         'style2.css',
     *         array('print.css', 'media' => 'print'),
     *     ),
     * 
     * @see  https://github.com/yiisoft/yii/issues/942
     */
    public function renderCoreScripts()
    {
        if($this->coreScripts===null && count($this->cdn) === 0)
            return;
        $cssFiles=$this->renderCdnFiles('css');
        $jsFiles=$this->renderCdnFiles('js');
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
        $this->onBeforeOptimization('css', self::POS_HEAD);
        $this->onOptimization('css', self::POS_HEAD);
        $this->onAfterOptimization('css', self::POS_HEAD);

        $this->onBeforeOptimization('js', self::POS_HEAD);
        $this->onOptimization('js', self::POS_HEAD);
        $this->onAfterOptimization('js', self::POS_HEAD);

        parent::renderHead($output);
    }

    /**
     * Inserts the scripts at the beginning of the body section.
     * 
     * @param string the output to be inserted with scripts.
     */
    public function renderBodyBegin(&$output)
    {
        $this->onBeforeOptimization('js', self::POS_BEGIN);
        $this->onOptimization('js', self::POS_BEGIN);
        $this->onAfterOptimization('js', self::POS_BEGIN);

        parent::renderBodyBegin($output);
    }

    /**
     * Inserts the scripts at the end of the body section.
     * 
     * @param string the output to be inserted with scripts.
     */
    public function renderBodyEnd(&$output)
    {
        $this->onBeforeOptimization('js', self::POS_END);
        $this->onOptimization('js', self::POS_END);
        $this->onAfterOptimization('js', self::POS_END);

        parent::renderBodyEnd($output);
    }
}