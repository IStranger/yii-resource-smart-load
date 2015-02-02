<?php

Yii::import('ext.resourcesmartload.*'); // todo refactor import of helper class

/**
 * RSmartLoadClientScript class file.
 *
 * @author  G.Azamat <m@fx4web.com>
 * @link    http://www.fx4.ru/
 * @link    http://github.com/IStranger/yiiResourceSmartLoad
 * @version 0.1 (2015-02-02)
 * @since   1.1.15
 */
class RSmartLoadClientScript extends CClientScript
{
    /**
     * JS path in global namespace to extension client side
     */
    const JS_GLOBAL_OBJ_PATH = 'window.yiiResourceSmartLoad';

    const HASH_METHOD_CRC32 = 'crc32b';
    const HASH_METHOD_MD5 = 'md5';

    /**
     * @var string[] Hashing methods for the resource file name
     */
    static $hashMethods = array(
        self::HASH_METHOD_CRC32,
        self::HASH_METHOD_MD5,
    );

    /**
     * @var string Current hashing method (for resource names)
     */
    public $hashMethod = self::HASH_METHOD_MD5;
    /**
     * @var string Enables log of registered/disabled resources (on server and client side)
     */
    public $enableLog = false;
    /**
     * @var string Activates "smart" disabling of resources on all pages.
     *             You can set =false, and call method {@link disableLoadedResources} in certain controllers/actions
     */
    public $activateOnAllPages = true;


    public function init()
    {
        $this->_publishExtensionResources();

        if ($this->activateOnAllPages) {
            $this->disableLoadedResources();
        }
    }

    protected function unifyScripts()
    {
        parent::unifyScripts();
        $this->afterUnifyScripts(); // raise our event
    }

    /**
     * Returns list of hashes of resources, which already loaded on client.
     * This list is sent every ajax-request in client variable "resourcesList"
     * (see. resourceSmartLoad.getLoadedResources() in resource_smart_load.js)
     *
     * @return array  List of hashes (hashed full name of the resource).If "client" variable not found, returns = array()
     * @see resourcesmartload/resource_smart_load.js
     */
    public function getLoadedResources()
    {
        $resourcesList = RSmartLoadHelper::getClientVar('resourcesList');
        return $resourcesList ? json_decode($resourcesList) : array();
    }

    /**
     * Returns plain array of registered resources (single files and from packets)
     *
     * @param array $type Types of resources array('js', 'css')
     * @return array
     */
    public function getRegisteredResources($type = array('js', 'css'))
    {
        // $this->scripts; $this->css;  - inline code blocks

        $resultList = array();
        // JS scripts
        if (in_array('js', $type)) {
            foreach ($this->scriptFiles as $pos => $filesGroup) {
                $resultList = array_merge($resultList, array_values($filesGroup));
            }
            foreach ($this->coreScripts as $packageName => $package) {
                $resultList = array_merge($resultList, $this->getPackageFileList($packageName, $package, 'js'));
            }
        }

        // CSS files
        if (in_array('css', $type)) {
            foreach ($this->coreScripts as $packageName => $package) {
                $resultList = array_merge($resultList, $this->getPackageFileList($packageName, $package, 'css'));
            }
            $resultList = array_merge($resultList, array_keys($this->cssFiles)); // Unlike JS-scripts for CSS does not specify the position of including.
        }
        return $resultList;
    }

    /**
     * Returns plain array of resources from given packet (builds full paths taking into account baseUrl) <br/>
     * The package must be REGISTERED, otherwise method returns not full path.
     * If the package is registered, method returns the full/assets path
     * (if necessary, the package will be published assetsManager).
     *
     * @param array  $packageName Name of package
     * @param array  $packageSpec Description {of} package, see {@link CClientScript::packages}
     * @param string $fileType    Type of files: 'css' or 'js'
     * @return array List of css/js files. If not exist, returns = array()
     * @see CClientScript::getPackageBaseUrl
     * @see CClientScript::renderCoreScripts
     */
    protected function getPackageFileList($packageName, $packageSpec, $fileType)
    {
        $baseUrl = $this->getPackageBaseUrl($packageName);
        return RSmartLoadHelper::createByFn(RSmartLoadHelper::value($packageSpec, $fileType, array()),
            function ($key, $value) use ($baseUrl) {
                return array($key, ($baseUrl ? $baseUrl . '/' : '') . $value);
            }
        );
    }

    /**
     * Disables loading (on client) of given files: <br/>
     * &mdash; Disables certain file by <b>full URL</b> or by <b>basename</b>.
     *         For example, "widgets.js" disables loading all resources with the same name, independently from  their paths.
     * &mdash; Disables all resource files by type, if given '*.css' or '*.js'
     *         (see {@link RSmartLoadClientScript::disableAllResources}) <br/><br/>
     * <b>ATTENTION!</b> Call this method disables loading <u><b>given</b></u> resources,
     * even if they will registered after calling this method.
     *
     * @param string[] $filePaths List of file paths (or their names without paths and slashes).
     * @see RSmartLoadClientScript::disableLoadedResources
     * @see RSmartLoadClientScript::disableAllResources
     * @see RSmartLoadClientScript::_disableResources
     */
    public function disableResources($filePaths)
    {
        $self = $this;
        $this->attachEventHandler('onAfterUnifyScripts', function ($event) use ($self, $filePaths) {
            $self->_disableResources($filePaths);
        });
    }

    /**
     * Disables loading of all resource files. <br/><br/>
     * <b>ATTENTION!</b> Call this method disables loading <u><b>all</b></u> resources,
     * even if they will registered after calling this method.
     *
     * @see RSmartLoadClientScript::disableResources
     * @see RSmartLoadClientScript::disableLoadedResources
     * @see RSmartLoadClientScript::_disableResources
     */
    public function disableAllResources()
    {
        $this->disableResources(array('*.js', '*.css'));
    }

    /**
     * Disables loading of resource files, which already loaded on client. <br/>
     * Used at AJAX requests. List of resource hashes obtained from "client" variable {@link Request::getClientVar}. <br/><br/>
     * <b>ATTENTION!</b> Call this method disables loading <u><b>"client"</b></u> resources,
     * even if they will registered after calling this method.
     *
     * @see RSmartLoadClientScript::disableResources
     * @see RSmartLoadClientScript::disableAllResources
     * @see RSmartLoadClientScript::_disableResources
     * @see frontend/www/resources/js/tvil.js
     */
    public function disableLoadedResources()
    {
        $self = $this;
        $this->attachEventHandler('onAfterUnifyScripts', function ($event) use ($self) {
            $self->_disableLoadedResources();
        });
    }


    /**
     * Raises event "AfterUnifyScripts". <br/>
     * Unification of scripts executed in the method {@link RSmartLoadClientScript::unifyScripts}.
     * In this time moment all resources is extracted to variables {@link cssFiles} and {@link scriptFiles},
     * executed script remap {@link remapScripts}, from these arrays deleted duplicate elements.
     */
    protected function afterUnifyScripts()
    {
        if ($this->hasEventHandler('onAfterUnifyScripts')) {
            $this->onAfterUnifyScripts(
                new CEvent($this, array(
                    'type' => 'core',
                ))
            );
        }
    }

    /**
     * Executes all handlers, that subscribed to event "AfterUnifyScripts". <br/>
     * Unification of scripts executed in the method {@link RSmartLoadClientScript::unifyScripts}.
     * In this time moment all resources is extracted to variables {@link cssFiles} and {@link scriptFiles},
     * executed script remap {@link remapScripts}, from these arrays deleted duplicate elements.
     *
     * @param CEvent $event
     * @see RSmartLoadClientScript::beforeRenderScripts
     */
    public function onAfterUnifyScripts($event)
    {
        $this->raiseEvent('onAfterUnifyScripts', $event);
    }

    /**
     * Disables loading of given files of resources from arrays {@link cssFiles} and {@link scriptFiles}. <br/>
     * &mdash; Disables certain file by <b>full URL</b> or by <b>basename</b>.
     *         For example, "widgets.js" disables loading all resources with the same name, independently from their paths.
     * &mdash; Disables all resource files by type, if given '*.css' or '*.js'
     *         (see {@link RSmartLoadClientScript::disableAllResources}) <br/><br/>
     * <b>ATTENTION!</b> This method cannot disable resources from packets, therefore it should be used,
     * when all packets extracted to named arrays (before script remap or before/after unification of scripts).
     *
     * @param string $filePaths List of file paths (or their names without paths and slashes).
     */
    private function _disableResources($filePaths)
    {
        $filterFunc = function ($url, $value) use ($filePaths) {
            return !in_array($url, $filePaths) && !in_array(basename($url), $filePaths);
        };

        // CSS:
        if (in_array('*.css', $filePaths)) {
            $this->cssFiles = array();
        } else {
            $this->cssFiles = RSmartLoadHelper::filterByFn($this->cssFiles, $filterFunc);
        }

        // JS:
        if (in_array('*.js', $filePaths)) {
            $this->scriptFiles = array();
        } else {
            foreach ($this->scriptFiles as $position => $scriptFiles) {
                $this->scriptFiles[$position] = RSmartLoadHelper::filterByFn($this->scriptFiles[$position], $filterFunc);
            }
        }
    }

    /**
     * Disables loading of resource files, which already loaded on client. <br/>
     * Used at AJAX requests. List of resource hashes obtained from "client" variable {@link Request::getClientVar}. <br/><br/>
     * <b>ATTENTION!</b> This method cannot disable resources from packets, therefore it should be used,
     * when all packets extracted to named arrays (before script remap or before/after unification of scripts).
     *
     * @return array  Associative array of files, which has been excluded from registered files
     *                (key - name of registered file, value - full URL taking into account CClientScript::scriptMap)
     * @see RSmartLoadClientScript::_disableResources
     * @see RSmartLoadClientScript::disableAllResources
     * @see CClientScript::scriptMap
     * @see frontend/www/resources/js/tvil.js
     */
    private function _disableLoadedResources()
    {
        $loadedResources = $this->getLoadedResources();
        $registeredResources = $this->getRegisteredResources();

        $excludeFiles = array();
        foreach ($registeredResources as $resource) {
            $hash = $this->_hashString($resource);
            if (in_array($hash, $loadedResources)) {
                $excludeFiles[$resource] = $resource;
            }
        }
        $this->_log(array('loaded' => $loadedResources, 'registeredResources' => $registeredResources, 'excluded' => $excludeFiles));
        $this->_disableResources($excludeFiles);
        return $excludeFiles;
    }

    /**
     * Hash of given string with current hash method {@link hashMethod}
     *
     * @param $str
     * @return string   hashed string
     */
    private function _hashString($str)
    {
        return hash($this->hashMethod, $str);
    }

    /**
     * Registers client resources of this extension and corresponding scripts
     */
    private function _publishExtensionResources()
    {
        $this->registerCoreScript('jquery');

        // Initialization of extension resources
        $assetsExt = dirname(__FILE__) . '/' . 'assets';
        $assetsPath = Yii::app()->getAssetManager()->publish($assetsExt);
        $this->registerScriptFile($assetsPath . '/' . 'resource_smart_load.js');

        // Initialization of client side (+ extension options export to client side)
        $extensionOptionsJson = json_encode(array(
            'hashMethod' => $this->hashMethod,
            'enableLog' => $this->enableLog,
            'activateOnAllPages' => $this->activateOnAllPages,
        ));
        $this->registerScript('resourceSmartLoadInitExtension',
            '(function () { ' .
            static::JS_GLOBAL_OBJ_PATH . '.initExtension(' . $extensionOptionsJson . '); ' .
            '})();', CClientScript::POS_HEAD);
    }

    /**
     * Logs given array (to system log)
     *
     * @param array  $resources
     * @param string $msg Message for log
     */
    private function _log($resources, $msg = 'Disabled following resources:')
    {
        if ($this->enableLog) {
            YiiBase::log($msg . PHP_EOL . var_export($resources, true), CLogger::LEVEL_TRACE, 'resourceSmartLoad');
        }
    }
} 