<?php

class EClientScriptTest extends CTestCase {

    const BASE_OUTPUT           = '<html><head></head><body></body></html>';

    const NODE_EXEC             = '/usr/local/bin/node';
    const COFFEESCRIPT_EXEC     = '/usr/local/bin/coffee';
    const UGLIFYJS_EXEC         = '/usr/local/bin/uglifyjs';
    const CLEANCSS_EXEC         = '/usr/local/bin/cleancss';

    const GZIP_EXEC             = '/usr/bin/gzip';
    const ZOPFLI_EXEC           = '/usr/bin/zopfli';

    /**
     * Создать и инициализировать asset manager, заменяя предыдущий компонент.
     * 
     * @param  array[optional]  $params 
     */
    protected function initAM(array $params = array())
    {
        $params = array_merge(array(
            'class' => 'ext.EAssetManager',
            'basePath' => realpath(__DIR__.'/../assets'),
            'baseUrl' => realpath(__DIR__.'/../assets'),
        ), $params);

        Yii::app()->setComponent('assetManager', $params);
    }
    
    /**
     * Создать и инициализировать новый инстанс client script.
     * По умолчанию все флаги сброшены.
     * 
     * @param  array[optional]  $params необязательный набор параметров для инициализации
     * @return EClientScript
     */
    protected function createCS(array $params = array())
    {
        $defaults = array(
            'combineScriptFiles'    => false,
            'combineCssFiles'       => false,
            'optimizeCssFiles'      => false,
            'optimizeScriptFiles'   => false,
            'saveGzippedCopy'       => false,
            'gzipExec'              => null,
            'zopfliExec'            => null,
            'nodeExec'              => null,
            'coffeeScriptExec'      => null,
            'uglifyjsExec'          => null,
            'cleancssExec'          => null,
            'disableInlineScripts'  => false,
            'useLazyLoad'           => false,
            'packages'              => array(
                'localStyles' => array(
                    'basePath' => 'testdata',
                    'css' => array(
                        'style1.css',
                        'style2.css',
                    ),
                ),
                'localPrintableStyles' => array(
                    'basePath' => 'testdata',
                    'css' => array(
                        array('style3.css', 'media' => 'print'),
                        array('style4.css', 'media' => 'print'),
                    ),
                ),
                'remoteStyles' => array(
                    'baseUrl' => 'http://example.com/css/',
                    'css' => array(
                        'styles1.css',
                        'styles2.css',
                    ),
                ),
                'localScripts' => array(
                    'basePath' => 'testdata',
                    'js' => array(
                        'script1.js',
                        'script2.js',
                    ),
                ),
                'remoteScripts' => array(
                    'baseUrl' => 'http://example.com/js/',
                    'js' => array(
                        'script1.js',
                        'script2.js',
                    ),
                ),
                'coffeeScripts' => array(
                    'basePath' => 'testdata',
                    'js' => array(
                        'script.coffee',
                    ),
                ),
            ),
        );

        $params = array_merge($defaults, $params);

        $cs = new EClientScript;
        foreach ($params as $k => $v) {
            $cs->$k = $v;
        }
        $cs->init();

        return $cs;
    }

    /**
     * Выполнить рендер страницы.
     * 
     * @param  EClientScript    $cs
     * @param  array[optional]  $packages необязательный массив наборов ресурсов
     * @param  string[optional] $output   код страницы, в которую нужно подключить ресурсы
     * @return string
     */
    protected function render(EClientScript $cs, array $packages = array(), $output = null)
    {
        foreach ($packages as $package) {
            $cs->registerPackage($package);
        }

        $output = $output ?: self::BASE_OUTPUT;
        $cs->render($output);

        return $output;
    }

    /**
     * Извлечь ссылки на стили из кода страницы.
     * 
     * @param  string $output
     * @return array
     */
    protected function getCssFiles($output)
    {
        $matches = array();
        if (preg_match_all('/href="([^"\?]+)/', $output, $matches)) {
            return $matches[1];
        }

        return array();
    }

    /**
     * Извлечь ссылки на скрипты из кода страницы.
     * 
     * @param  string $output
     * @return array
     */
    protected function getScriptFiles($output)
    {
        $matches = array();
        if (preg_match_all('/src="([^"\?]+)/', $output, $matches)) {
            return $matches[1];
        }

        return array();
    }

    public function setUp()
    {
        $this->initAM();
    }

    public function tearDown()
    {
        passthru('rm -rf '.escapeshellarg(Yii::app()->assetManager->basePath).'/*');
    }

    public function testCssMedia()
    {
        $cs = $this->createCS();
        $output = $this->render($cs, array(
            'localPrintableStyles',
        ));
        $this->assertContains('media="print"', $output);
    }

    public function testWrongPaths()
    {
        $cs = $this->createCS(array(
            'nodeExec' => 'path',
        ));
        $this->render($cs);
        $this->assertFalse($cs->nodeExec);
        $this->assertFalse($cs->coffeeScriptExec);
        $this->assertFalse($cs->uglifyjsExec);
        $this->assertFalse($cs->cleancssExec);

        $cs = $this->createCS(array(
            'coffeeScriptExec' => 'path',
        ));
        $this->render($cs);
        $this->assertFalse($cs->coffeeScriptExec);

        $cs = $this->createCS(array(
            'uglifyjsExec' => 'path',
        ));
        $this->render($cs);
        $this->assertFalse($cs->uglifyjsExec);

        $cs = $this->createCS(array(
            'cleancssExec' => 'path',
        ));
        $this->render($cs);
        $this->assertFalse($cs->cleancssExec);

        $cs = $this->createCS(array(
            'gzipExec' => 'path',
        ));
        $this->render($cs);
        $this->assertFalse($cs->gzipExec);

        $cs = $this->createCS(array(
            'zopfliExec' => 'path',
        ));
        $this->render($cs);
        $this->assertFalse($cs->zopfliExec);
    }

    public function testSaveGzippedCopyWithGzip()
    {
        $this->assertFileExists(self::GZIP_EXEC);

        $cs = $this->createCS(array(
            'gzipExec' => self::GZIP_EXEC,
        ));
        $output = $this->render($cs, array(
            'localStyles',
            'localScripts',
        ));
        $styles = $this->getCssFiles($output);
        $this->assertCount(2, $styles);
        $this->assertFileExists($styles[0]);
        $this->assertFileNotExists($styles[0].'.gz');
        $scritps = $this->getScriptFiles($output);
        $this->assertCount(2, $scritps);
        $this->assertFileExists($scritps[0]);
        $this->assertFileNotExists($scritps[0].'.gz');

        $cs = $this->createCS(array(
            'gzipExec' => self::GZIP_EXEC,
            'saveGzippedCopy' => true,
        ));
        $output = $this->render($cs, array(
            'localStyles',
            'localScripts',
        ));
        $styles = $this->getCssFiles($output);
        $this->assertCount(2, $styles);
        $this->assertFileExists($styles[0]);
        $this->assertFileExists($styles[0].'.gz');
        $scritps = $this->getScriptFiles($output);
        $this->assertCount(2, $scritps);
        $this->assertFileExists($scritps[0]);
        $this->assertFileExists($scritps[0].'.gz');
    }

    public function testSaveGzippedCopyWithZopfli()
    {
        $this->assertFileExists(self::ZOPFLI_EXEC);

        $cs = $this->createCS(array(
            'zopfliExec' => self::ZOPFLI_EXEC,
        ));
        $output = $this->render($cs, array(
            'localStyles',
            'localScripts',
        ));
        $styles = $this->getCssFiles($output);
        $this->assertCount(2, $styles);
        $this->assertFileExists($styles[0]);
        $this->assertFileNotExists($styles[0].'.gz');
        $scritps = $this->getScriptFiles($output);
        $this->assertCount(2, $scritps);
        $this->assertFileExists($scritps[0]);
        $this->assertFileNotExists($scritps[0].'.gz');

        $cs = $this->createCS(array(
            'zopfliExec' => self::ZOPFLI_EXEC,
            'saveGzippedCopy' => true,
        ));
        $output = $this->render($cs, array(
            'localStyles',
            'localScripts',
        ));
        $styles = $this->getCssFiles($output);
        $this->assertCount(2, $styles);
        $this->assertFileExists($styles[0]);
        $this->assertFileExists($styles[0].'.gz');
        $scritps = $this->getScriptFiles($output);
        $this->assertCount(2, $scritps);
        $this->assertFileExists($scritps[0]);
        $this->assertFileExists($scritps[0].'.gz');
    }

    public function testCombineCssFiles()
    {
        $cs = $this->createCS(array(
            'combineCssFiles' => false,
        ));
        $output = $this->render($cs, array(
            'localStyles',
        ));
        $styles = $this->getCssFiles($output);
        $this->assertCount(2, $styles);

        $cs = $this->createCS(array(
            'combineCssFiles' => true,
        ));
        $output = $this->render($cs, array(
            'localStyles',
        ));
        $styles = $this->getCssFiles($output);
        $this->assertCount(1, $styles);
        $this->assertFileExists($styles[0]);

        $cs = $this->createCS(array(
            'combineCssFiles' => true,
        ));
        $output = $this->render($cs, array(
            'localStyles',
            'localPrintableStyles',
        ));
        $styles = $this->getCssFiles($output);
        $this->assertCount(2, $styles);

        $cs = $this->createCS(array(
            'combineCssFiles' => true,
        ));
        $output = $this->render($cs, array(
            'localStyles',
            'localPrintableStyles',
            'remoteStyles',
        ));
        $styles = $this->getCssFiles($output);
        $this->assertCount(4, $styles);
    }

    public function testCombineScriptFiles()
    {
        $cs = $this->createCS(array(
            'combineScriptFiles' => false,
        ));
        $output = $this->render($cs, array(
            'localScripts',
        ));
        $scripts = $this->getScriptFiles($output);
        $this->assertCount(2, $scripts);

        $cs = $this->createCS(array(
            'combineScriptFiles' => true,
        ));
        $output = $this->render($cs, array(
            'localScripts',
        ));
        $scripts = $this->getScriptFiles($output);
        $this->assertCount(1, $scripts);
        $this->assertFileExists($scripts[0]);

        $cs = $this->createCS(array(
            'combineScriptFiles' => true,
        ));
        $output = $this->render($cs, array(
            'remoteScripts',
        ));
        $scripts = $this->getScriptFiles($output);
        $this->assertCount(2, $scripts);

        $cs = $this->createCS(array(
            'combineScriptFiles' => true,
        ));
        $output = $this->render($cs, array(
            'localScripts',
            'remoteScripts',
        ));
        $scripts = $this->getScriptFiles($output);
        $this->assertCount(3, $scripts);
    }

    public function testOptimizeCssFiles()
    {
        $this->assertFileExists(self::NODE_EXEC, 'Node.js is not found');
        $this->assertFileExists(self::CLEANCSS_EXEC, 'clean-css is not found');

        $cs = $this->createCS(array(
            'nodeExec' => self::NODE_EXEC,
            'cleancssExec' => self::CLEANCSS_EXEC,
        ));
        $output = $this->render($cs, array(
            'localStyles',
        ));
        $styles = $this->getCssFiles($output);
        $this->assertCount(2, $styles);
        $this->assertFileExists($styles[0]);
        $this->assertContains('comment', file_get_contents($styles[0]));

        $cs = $this->createCS(array(
            'nodeExec' => self::NODE_EXEC,
            'cleancssExec' => self::CLEANCSS_EXEC,
            'optimizeCssFiles' => true,
        ));
        $output = $this->render($cs, array(
            'localStyles',
        ));
        $styles = $this->getCssFiles($output);
        $this->assertCount(2, $styles);
        $this->assertFileExists($styles[0]);
        $this->assertNotContains('comment', file_get_contents($styles[0]));
    }

    public function testOptimizeScriptFiles()
    {
        $this->assertFileExists(self::NODE_EXEC, 'Node.js is not found');
        $this->assertFileExists(self::UGLIFYJS_EXEC, 'Uglify.js is not found');

        $cs = $this->createCS(array(
            'nodeExec' => self::NODE_EXEC,
            'uglifyjsExec' => self::UGLIFYJS_EXEC,
        ));
        $output = $this->render($cs, array(
            'localScripts',
        ));
        $scripts = $this->getScriptFiles($output);
        $this->assertCount(2, $scripts);
        $this->assertFileExists($scripts[0]);
        $this->assertContains('comment', file_get_contents($scripts[0]));

        $cs = $this->createCS(array(
            'nodeExec' => self::NODE_EXEC,
            'uglifyjsExec' => self::UGLIFYJS_EXEC,
            'optimizeScriptFiles' => true,
        ));
        $output = $this->render($cs, array(
            'localScripts',
        ));
        $scripts = $this->getScriptFiles($output);
        $this->assertCount(2, $scripts);
        $this->assertFileExists($scripts[0]);
        $this->assertNotContains('comment', file_get_contents($scripts[0]));
    }

    public function testAssetVersion()
    {
        $this->initAM(array(
            'assetVersion' => 'RELEASE',
        ));
        
        $cs = $this->createCS();
        $output = $this->render($cs, array(
            'localStyles',
        ));
        $this->assertContains('RELEASE', $output);

        $this->createCS();
        $output = $this->render($cs, array(
            'localScripts',
        ));
        $this->assertContains('RELEASE', $output);
        
        $cs = $this->createCS(array(
            'combineCssFiles' => true,
        ));
        $output = $this->render($cs, array(
            'localStyles',
        ));
        $this->assertContains('RELEASE', $output);

        $this->createCS(array(
            'combineScriptFiles' => true,
        ));
        $output = $this->render($cs, array(
            'localScripts',
        ));
        $this->assertContains('RELEASE', $output);
    }

    public function testDisableInlineScripts()
    {
        $cs = $this->createCS();
        $cs->registerScript('inline_head', '// this is head script', $cs::POS_HEAD);
        $cs->registerScript('inline_begin', '// this is begin script', $cs::POS_BEGIN);
        $cs->registerScript('inline_ready', '// this is ready script', $cs::POS_READY);
        $cs->registerScript('inline_onload', '// this is onload script', $cs::POS_LOAD);
        $cs->registerScript('inline_end', '// this is end script', $cs::POS_END);
        $output = $this->render($cs);
        $this->assertContains('this', $output);

        $cs = $this->createCS(array(
            'disableInlineScripts' => true,
        ));
        $cs->registerScript('inline_head', '// this is head script', $cs::POS_HEAD);
        $cs->registerScript('inline_begin', '// this is begin script', $cs::POS_BEGIN);
        $cs->registerScript('inline_ready', '// this is ready script', $cs::POS_READY);
        $cs->registerScript('inline_onload', '// this is onload script', $cs::POS_LOAD);
        $cs->registerScript('inline_end', '// this is end script', $cs::POS_END);
        $output = $this->render($cs);
        $this->assertNotContains('this', $output);
        $scripts = $this->getScriptFiles($output);
        $this->assertCount(4, $scripts);
    }

    public function testUseLazyLoad()
    {
        $cs = $this->createCS();
        $output = $this->render($cs, array(
            'localStyles',
            'localScripts',
        ));
        $styles = $this->getCssFiles($output);
        $scripts = $this->getScriptFiles($output);
        $this->assertCount(2, $styles);
        $this->assertCount(2, $scripts);

        $cs = $this->createCS(array(
            'useLazyLoad' => true
        ));
        $output = $this->render($cs, array(
            'localStyles',
            'localScripts',
        ));
        $styles = $this->getCssFiles($output);
        $scripts = $this->getScriptFiles($output);
        $this->assertCount(0, $styles);
        $this->assertCount(1, $scripts);
    }

    public function testCoffeeScript()
    {
        $this->assertFileExists(self::COFFEESCRIPT_EXEC);

        $cs = $this->createCS();
        $output = $this->render($cs, array(
            'coffeeScripts',
        ));
        $scripts = $this->getScriptFiles($output);
        $this->assertCount(1, $scripts);
        $this->assertRegExp('/\.coffee$/', $scripts[0]);

        $cs = $this->createCS(array(
            'nodeExec' => self::NODE_EXEC,
            'coffeeScriptExec' => self::COFFEESCRIPT_EXEC,
        ));
        $output = $this->render($cs, array(
            'coffeeScripts',
        ));

        $scripts = $this->getScriptFiles($output);
        $this->assertCount(1, $scripts);
        $this->assertRegExp('/\.js$/', $scripts[0]);
        $this->assertFileExists($scripts[0]);
        $this->assertFileExists(preg_replace('/\.js$/', '.coffee', $scripts[0]));
    }

}