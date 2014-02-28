<?php
/**
 * Extended asset manager for {@link EClientScript}.
 * 
 * @author  Martin Stolz <herr.offizier@gmail.com>
 * @package ext.yiisset
 */

/**
 * Asset manager with {@link assetVersion} support.
 */
class EAssetManager extends CAssetManager
{
    /**
     * Asset version, if not null, will be appended to asset directory name.
     * 
     * @var string
     */
	public $assetVersion = null;

    /**
     * Add asset version to path hash
     * 
     * @param  string $path
     * @return string
     */
	protected function hash($path)
	{
		return parent::hash($path).($this->assetVersion ? '-'.$this->assetVersion : '');
	}

}