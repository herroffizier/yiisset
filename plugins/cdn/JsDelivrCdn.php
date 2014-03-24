<?php

class JsDelivrCdn extends CBehavior {
    
    public function renderJsDelivrFiles($type, $scripts)
    {
        $protocol = (!empty($_SERVER['HTTPS']) ? 'https' : 'http');

        $combined = 
            ($type === 'css' && $this->owner->combineCssFiles) 
            || ($type === 'js' && $this->owner->combineScriptFiles);

        if ($combined) {
            return array(
                $protocol
                .'://cdn.jsdelivr.net/g/'
                .implode(',', $scripts)
            );
        }
        else {
            return array_map(function($script) use ($type, $protocol) {
                $matches = array();
                if (preg_match('/([^@]+)@(.+)/', $script, $matches)) {
                    list(, $script, $version) = $matches;
                }
                else {
                    $version = 'latest';
                }

                return
                    $protocol
                    .'://cdn.jsdelivr.net/'
                    .$script.'/'
                    .$version.'/'
                    .'mainfile';
            }, $scripts);
        }
        
    }

}