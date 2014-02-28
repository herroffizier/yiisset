<?php

class EClientScriptTest extends CTestCase {

    protected function getBaseOutput()
    {
        return '<html><head></head><body></body></html>';
    }

    protected function initAM(array $params = array())
    {
        $params = array_merge(array(
            'class' => 'ext.EAssetManager',
            'basePath' => realpath(__DIR__.'/../assets'),
            'baseUrl' => realpath(__DIR__.'/../assets'),
        ), $params);

        Yii::app()->setComponent('assetManager', $params);
    }
    
    protected function createCS(array $params)
    {
        $defaults = array(
            'combineScriptFiles'    => false,
            'combineCssFiles'       => false,
            'optimizeCssFiles'      => false,
            'optimizeScriptFiles'   => false,
            'saveGzippedCopy'       => false,
            'zopfliExec'            => null,
            'nodeExec'              => null,
            'coffeeScriptExec'      => null,
            'uglifyjsExec'          => null,
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

    protected function render(EClientScript $cs, array $packages = array(), $output = null)
    {
        foreach ($packages as $package) {
            $cs->registerPackage($package);
        }

        $output = $output ?: $this->getBaseOutput();
        $cs->render($output);

        return $output;
    }

    protected function getCssFiles($output)
    {
        $matches = array();
        if (preg_match_all('/href="([^"\?]+)/', $output, $matches)) {
            return $matches[1];
        }

        return array();
    }

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


}