<?php
/**
 * Extended client script class with a lot of useful (and useless)
 * additional stuff.
 * 
 * @author  Martin Stolz <herr.offizier@gmail.com>
 * @package ext.yiisset
 */

/**
 * Extended client script.
 * Better to use in conjunction with {@link EAssetManager}
 */
class EClientScript extends CClientScript
{

	/**
	 * Whether css combination is enabled.
	 * 
	 * @var boolean
	 */
	public $combineCssFiles = true;

	/**
	 * Whether css optimization is enabled.
	 * 
	 * @var boolean
	 */
	public $optimizeCssFiles = true;

	/**
	 * Combined css file name.
	 * 
	 * @var string
	 */
	public $cssFileName = 'style.css';

	/**
	 * Whether script combination is enabled.
	 * 
	 * @var boolean
	 */
	public $combineScriptFiles = true;

	/**
	 * Whether script optimization is enabled.
	 * 
	 * @var boolean
	 */
	public $optimizeScriptFiles = true;

	/**
	 * Combined script file name.
	 * 
	 * @var string
	 */
	public $scriptFileName = 'script.js';

	/**
	 * Whether gzipped version of combined files generating is enabled.
	 * Useful for nginx's gzip_static, works only with {@link combineScriptFiles} = true 
	 * or {@link combineCssFiles} = true).
	 * 
	 * @var boolean
	 */
	public $saveGzippedCopy = true;

	/**
	 * Path to zopfli binary.
	 * Zopfli may be used as more effective alternative for Gzip when {@link saveGzippedCopy} = true.
	 * If points to nonexistent file, Zopfli precompression will be silently
	 * disabled.
	 * Set to false to disable Zopfli manually.
	 *
	 * @see https://code.google.com/p/zopfli/
	 * @var mixed 
	 */
	public $zopfliExec = '/usr/bin/zopfli';

	/**
	 * Path to node.js executable.
	 * Required for CoffeeScript compiler and uglify.js.
	 * If points to nonexistent file, dependent features will be silenlty
	 * disabled.
	 * Set to false to disable all dependent features manually.
	 * 
	 * @var mixed
	 */
	public $nodeExec = '/usr/local/bin/node';

	/**
	 * Path to CoffeeScript compiler.
	 * If set to correct path, compiles all *.coffee files to JS.
	 * Pay attention, each file will be compiled separately.
	 * Set to false to disable CoffeeScript compiling.
	 * 
	 * Requires {@link nodeExec} to be set.
	 * 
	 * @var mixed
	 */
	public $coffeeScriptExec = '/usr/local/bin/coffee';

	/**
	 * Path to uglify.js.
	 * Set to false to disable uglifying scripts.
	 * 
	 * Requires {@link nodeExec} to be set to correct path 
	 * and {@link optimizeScriptFiles} set to true.
	 * 
	 * @var mixed
	 */
	public $uglifyjsExec = '/usr/local/bin/uglifyjs';

	/**
	 * Whether to put inline scripts (registered with {@link registerScript})
	 * into file.
	 * Quite useless. :)
	 * 
	 * @var boolean
	 */
	public $disableInlineScripts = true;

	/**
	 * Whether to use LazyLoad script to enable parallel resource downloading.
	 * This feature may be useful when using slow CDNs like Facebook's one.
	 * Extension is bundled with modified version of LazyLoad.
	 *
	 * @see https://github.com/herroffizier/lazyload
	 * 
	 * @var boolean
	 */
	public $useLazyLoad = true;

	/**
	 * Whether LazyLoaded registered.
	 * 
	 * @var boolean
	 */
	protected $lazyLoadRegistered = false;

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

		// check paths to executables and build features list
		$features = array();

		if (!$this->nodeExec || !file_exists($this->nodeExec)) {
			Yii::trace('No node.js executable found, disabling CoffeeScript and UglifyJS');
			$this->nodeExec = false;
			$this->coffeeScriptExec = false;
			$this->uglifyjsExec = false;
		}

		if ($this->coffeeScriptExec && !file_exists($this->coffeeScriptExec)) {
			Yii::trace('No CoffeeScript executable found, disabling CoffeeScript compilation');
			$this->coffeeScriptExec = false;
		}

		if ($this->uglifyjsExec && !file_exists($this->uglifyjsExec)) {
			Yii::trace('No UglifyJS executable found, disabling UglifyJS optimization');
			$this->uglifyjsExec = false;
		}

		if ($this->zopfliExec && !file_exists($this->zopfliExec)) {
			Yii::trace('No Zopfli executable found, disabling Zopfli compression');
			$this->zopfliExec = false;
		}

		$features = array_keys(array_filter(array(
			'coffeescript'		 => $this->coffeeScriptExec,
			'uglifyjs'			 => $this->uglifyjsExec && $this->optimizeScriptFiles && $this->combineScriptFiles,
			'gzip precompress'	 => $this->saveGzippedCopy && ($this->combineCssFiles || $this->combineScriptFiles) && !$this->zopfliExec,
			'zopfli precompress' => $this->saveGzippedCopy && ($this->combineCssFiles || $this->combineScriptFiles) && $this->zopfliExec,
			'combining js'		 => $this->combineScriptFiles,
			'combining css'		 => $this->combineCssFiles,
			'saving inline js'	 => $this->disableInlineScripts,
			'lazyload'			 => $this->useLazyLoad,
		)));
		
		if ($features) {
			Yii::trace('yiisset is running with '.implode(', ', $features));
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
	protected function getAssetVersion($path)
	{
		return 
			(Yii::app()->assetManager instanceof EAssetManager && Yii::app()->assetManager->assetVersion)
				? Yii::app()->assetManager->assetVersion
				: (file_exists($path) ? filemtime($path) : time());
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
		$ext = ($type === '' ? '' : '-' . $type) . '-' . $this->hash($raw);
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
	 * @param string $from source url, begin with slash and not end width slash.
	 * @param string $to dest url
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
		if (!file_exists($copy)) return false;
		return filemtime($copy) > filemtime($file);
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
	 * Если указан $toFile, то в качесве $touchFile используется он.
	 *
	 * @throws CException если после выполнения команды результирующий файл не был создан
	 *
	 * @param  string 				$tool 			название команды, используется для лога
	 * @param  string  				$command 		команда, может содержать метки #FROM_FILE# и #TO_FILE#
	 * @param  string  				$fromFile 		исходный (и результирующий, если $toFile = null) файл
	 * @param  string[optional]  	$toFile 		результирующий файл
	 * @param  boolean[optional] 	$removeSource 	удалять ли исходный файл в случае успеха
	 */
	protected function optimizeFile($tool, $command, $fromFile, $toFile = null, $removeSource = true) 
	{
		$touchFile = $toFile ?: $fromFile.'.processed.'.preg_replace('/\s+/', '', $tool);

		if ($this->isNewer($fromFile, $touchFile)) {
			Yii::trace($touchFile.' is already processed with '.$tool.'.');
			return;
		}

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
				$tool.' saves '.number_format(filesize($fromFile) - filesize($toFile))." bytes\nfor "
				.pathinfo($useTempFile ? $toFile : $fromFile, PATHINFO_BASENAME).'.'
			);

			if ($useTempFile || $removeSource) {
				unlink($fromFile);
			}
		}
		else {
			if ($useTempFile) {
				rename($fromFile, $toFile);
			}

			throw new CException($tool.' failed to optimize '.($useTempFile ? $toFile : $fromFile).'.');
		}

		touch($touchFile);
	}

	/**
	 * Скомпилировать каждый .coffee файл в указанной позиции в JS.
	 * 
	 * @param  int $position
	 */
	protected function compileCoffeeScript($position)
	{
		if (!$this->coffeeScriptExec || empty($this->scriptFiles[$position])) return;
		
		$scriptFiles = array();
		
		foreach ($this->scriptFiles[$position] as $index => $script) {
			if (
				!preg_match('/\.coffee$/', $script) 
				|| !($scriptFile = $this->getLocalPath($script))
			) {
				$scriptFiles[$index] = $script;
				continue;
			}

			$compiledScript = mb_substr($script, 0, mb_strlen($script) - 6).'js';
			$compiledScriptFile = mb_substr($scriptFile, 0, mb_strlen($scriptFile) - 6).'js';

			$scriptFiles[$compiledScript] = $compiledScript;

			if ($this->isNewer($scriptFile, $compiledScriptFile)) {
				Yii::trace($scriptFile.' is already compiled to '.$compiledScriptFile);
				continue;
			}

			$cmd = 
				escapeshellcmd(
					$this->nodeExec.' '.escapeshellarg($this->coffeeScriptExec)
					.' -o '.escapeshellarg(dirname($scriptFile).'/')
					.' -c '.escapeshellarg($scriptFile)
					
				).' 2>&1';
			
			$output = shell_exec($cmd);
			if ($output) {
				throw new Exception('Coffee compiler ('.$cmd.') output: '.$output);
			}
			else {
				Yii::trace('Coffee compiler ('.$cmd.') completed successfully.');
			}
		}

		$this->scriptFiles[$position] = $scriptFiles;
	}

	/**
	 * Сжать JS при помощи Uglify.js
	 * 
	 * @param  string $file
	 */
	protected function uglifyScriptFile($file)
	{
		if (!$this->uglifyjsExec || !$this->optimizeScriptFiles) return;

		$cmd = $this->nodeExec.' '.escapeshellarg($this->uglifyjsExec).' #FROM_FILE# -o #TO_FILE#';
		$this->optimizeFile('Uglify.js', $cmd, $file);
	}

	/**
	 * Создать gzipped копию файла.
	 * Если возможно, используется Zopfli, в противном случае - gzip.
	 * 
	 * @param  string $file
	 */
	protected function createGzippedCopy($file)
	{
		if (!$this->saveGzippedCopy) return;

		$gzippedFile = $file.'.gz';

		if (false && $this->zopfliExec) {
			$tool = 'Zopfli';
			$cmd = $this->zopfliExec.' #FROM_FILE#';
		}
		else {
			$tool = 'Gzip';
			$cmd = 'gzip --best -c #FROM_FILE# > #TO_FILE#';
		}
		
		$this->optimizeFile($tool, $cmd, $file, $gzippedFile);
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
	 * Загружать все файлы в указанной позиции через LazyLoad.
	 * 
	 * @param  int $position
	 */
	protected function lazyLoad($position)
	{
		if (!isset($this->scriptFiles[$position]) || count($this->scriptFiles[$position]) < 2) return;

		$scriptFiles = array_keys($this->scriptFiles[$position]);
		$code = 'LazyLoad.js('.CJSON::encode($scriptFiles).');';
		$this->registerScript('lazyLoad_'.$position, $code, $position);

		$this->scriptFiles[$position] = array();

		$this->registerLazyLoad($position);
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
	}

	/**
	 * Объединить css-файлы в один файл.
	 * Код  взят из yii-EClientScript и немного дополнен.
	 */
	protected function combineCssFiles()
	{
		// Check the need for combination
		if (count($this->cssFiles) < 2) {
			return;
		}
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
							$fileBuffer .= "/*** CSS File: {$url}";
							if ($this->optimizeCssFiles && strpos($file, '.min.') === false && strpos($file, '.pack.') === false) {
								$fileBuffer .= ", Original size: " . number_format(strlen($contents)) . ", Compressed size: ";
								$contents = $this->optimizeCssCode($contents);
								$fileBuffer .= number_format(strlen($contents));
							}
							$fileBuffer .= " ***/\n";
							$fileBuffer .= $contents . "\n\n";
						}
					}
					Yii::trace('Saving merged css into '.$fpath);
					file_put_contents($fpath, $charsetLine . $fileBuffer);
					$this->createGzippedCopy($fpath);
				}
				else {
					Yii::trace('Merged css is already saved in '.$fpath);
				}
				// real url of combined file
				$url = Yii::app()->assetManager->baseUrl . '/' . $fname . '?' . $this->getAssetVersion($fpath);
			}
			$this->cssFiles[$url] = $media;
		}
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
		if (!isset($this->scriptFiles[$position]) || count($this->scriptFiles[$position]) < 2) {
			return;
		}
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
				$this->uglifyScriptFile($fpath);
				$this->createGzippedCopy($fpath);
			}
			else {
				Yii::trace('Merged script is already saved in '.$fpath);
			}
			// add the combined file into scriptFiles
			$url = Yii::app()->assetManager->baseUrl . '/' . $fname . '?' . $this->getAssetVersion($fpath);
			$scriptName[] = $url;
			$scriptValue[] = $url;
		}
		// use new scriptFiles list replace old ones
		$this->scriptFiles[$position] = array_combine($scriptName, $scriptValue);
	}

	/**
	 * Optmize css, strip any spaces and newline
	 * 
	 * @param string $data input css data
	 * @return string optmized css data
	 */
	private function optimizeCssCode($code)
	{
		require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'CssMin.php';
		return CssMin::minify($code, array(), array('CompressUnitValues' => true));
	}

	/**
	 * В метод CClientScript::renderCoreScripts добавлена поддержка 
	 * аттрибута media для css.
	 * 
	 * Теперь в описании package наравне с прежним синтаксисом можно исспользовать
	 * такую форму записи:
	 * 		'css' => array(
	 * 			'style1.css',
	 * 			'style2.css',
	 * 			array('print.css', 'media' => 'print'),
	 * 		),
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
		if ($this->enableJavaScript) {
			$this->compileCoffeeScript(self::POS_HEAD);

			if ($this->disableInlineScripts) {
				$this->saveInlineCodeToFile(self::POS_HEAD);
			}

			if ($this->combineScriptFiles) {
				$this->combineScriptFiles(self::POS_HEAD);
			}

			if ($this->useLazyLoad) {
				$this->lazyLoad(self::POS_HEAD);
			}
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
		$this->compileCoffeeScript(self::POS_BEGIN);

		if ($this->disableInlineScripts) {
			$this->saveInlineCodeToFile(self::POS_BEGIN);
		}

		if ($this->combineScriptFiles) {
			$this->combineScriptFiles(self::POS_BEGIN);
		}

		if ($this->useLazyLoad) {
			$this->lazyLoad(self::POS_BEGIN);
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
		$this->compileCoffeeScript(self::POS_END);
		
		if ($this->disableInlineScripts) {
			$this->saveInlineCodeToFile(self::POS_END);
		}

		if ($this->combineScriptFiles) {
			$this->combineScriptFiles(self::POS_END);
		}

		if ($this->useLazyLoad) {
			$this->lazyLoad(self::POS_END);
		}

		parent::renderBodyEnd($output);
	}
}
