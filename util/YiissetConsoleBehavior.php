<?php

class YiissetConsoleBehavior extends CBehavior
{
    
    public function getAssetManager()
    {
        return Yii::app()->getComponent('assetManager');
    }

}