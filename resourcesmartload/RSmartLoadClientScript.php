<?php

/**
 * RSmartLoadClientScript class file.
 *
 * @author  G.Azamat <m@fx4web.com>
 * @link    http://fx4.ru/
 * @link    https://github.com/IStranger/yii-resource-smart-load
 * @version 0.11 (2015-02-07)
 * @since   1.1.14
 */
class RSmartLoadClientScript extends CClientScript
{
    /**
     * JS path in global namespace to extension client side
     */
    const JS_GLOBAL_OBJ_PATH = 'window.yiiResourceSmartLoad';

    /**
     * Path alias, which used for import {@link Yii::import} of extension classes
     */
    const PATH_ALIAS = 'resourcesmartload';

    const HASH_METHOD_CRC32 = 'crc32b';
    const HASH_METHOD_MD5 = 'md5';

    const EVENT_AFTER_UNIFY_SCRIPTS = 'onAfterUnifyScripts';

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

    /**
     * @var string[] List of resources, that always should be loaded on client. Each resource can be presented: <br/>
     *               - resource file: as <b>hash</b>, or <b>full URL</b>, or <b>basename</b>.<br/>
     *               - resource inline block: as <b>hash</b>, or <b>resource content</b>.
     */
    public $alwaysReloadableResources = array();

    public function init()
    {
        $this->_setExtensionPathAliasIfUndefined();
        Yii::import(self::PATH_ALIAS . '.*');

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
     * Raises event {@link RSmartLoadClientScript::EVENT_AFTER_UNIFY_SCRIPTS}. <br/>
     * Unification of scripts executed in the method {@link RSmartLoadClientScript::unifyScripts}.
     * In this time moment all resources is extracted to variables {@link cssFiles} and {@link scriptFiles},
     * executed script remap {@link remapScripts}, from these arrays deleted duplicate elements.
     */
    protected function afterUnifyScripts()
    {
        if ($this->hasEventHandler(self::EVENT_AFTER_UNIFY_SCRIPTS)) {
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
    protected function onAfterUnifyScripts($event)
    {
        $this->raiseEvent(self::EVENT_AFTER_UNIFY_SCRIPTS, $event);
    }

    /**
     * Returns list of hashes of resources, which already loaded on client.
     * This list is sent every ajax-request in "client" variable "resourcesList" {@link RSmartLoadHelper::getClientVar}
     * (see. resourceSmartLoad.getLoadedResources() in resource_smart_load.js)
     *
     * @return string[]     List of hashes (hashed full name of the resource).
     *                      If "client" variable not found, returns empty array()
     * @see resourcesmartload/resource_smart_load.js
     */
    public function getLoadedResourcesHashes()
    {
        $resourcesList = RSmartLoadHelper::getClientVar('resourcesList');
        return $resourcesList
            ? json_decode($resourcesList)
            : array();
    }

    /**
     * Disables loading of all resource files.
     *
     * <b>ATTENTION!</b> Calling this method disables loading <u><b>all</b></u> resources,
     * even if they will registered after calling this method.
     *
     * @see RSmartLoadClientScript::disableLoadedResources
     */
    public function disableAllResources()
    {
        $self = $this;
        $this->attachEventHandler(self::EVENT_AFTER_UNIFY_SCRIPTS, function ($event) use ($self) {
            $self->excludeCSSFiles('*.css');
            $self->excludeJSFiles('*.js');
            //$self->css = array();         // todo add disabling of inline blocks
            //$self->scripts = array();
        });
    }

    /**
     * Disables loading of resource files, which already loaded on client. <br/>
     * Used at AJAX requests. List of resource hashes obtained from "client" variable {@link Request::getClientVar}.
     *
     * <b>ATTENTION!</b> Calling this method disables loading <u><b>"client"</b></u> resources,
     * even if they will registered after calling this method.
     *
     * @see RSmartLoadClientScript::disableAllResources
     */
    public function disableLoadedResources()
    {
        $self = $this;
        $this->attachEventHandler(self::EVENT_AFTER_UNIFY_SCRIPTS, function ($event) use ($self) {
            $hashList = $self->getLoadedResourcesHashes();
            $self->excludeCSSFiles($hashList);
            $self->excludeJSFiles($hashList);
            //$self->css = array();             // todo add disabling of inline blocks
            //$self->scripts = array();
        });
    }


    /**
     * Excludes from array {@link cssFiles} (registered CSS files) given files of resources. <br/>
     *
     * - Excludes certain file by <b>hash</b>, or by <b>full URL</b>, or by <b>basename</b>.<br/>
     *   For example, "widgets.css" disables loading all resources with the same name, independently from their paths.
     * - Excludes all resource files, if given '*.css'
     *
     * <b>ATTENTION!</b> This method cannot exclude resources from registered packets, therefore it should be used,
     * when all packets extracted to target array (before script remap or before/after unification of scripts).
     *
     * @param string $excludeList List of resource hashes, or file URLs, or their names without paths and slashes.
     */
    protected function excludeCSSFiles($excludeList)
    {
        if (in_array('*.css', $excludeList)) {
            $this->cssFiles = array();
        } else {
            $self = $this;
            $filterFunc = function ($url, $value) use ($self, $excludeList) {
                return $self->_shouldBeLoadedFile($url, $excludeList);
            };
            $this->cssFiles = RSmartLoadHelper::filterByFn($this->cssFiles, $filterFunc);
        }
    }

    /**
     * Excludes from array {@link scriptFiles} (registered JS files) given files of resources. <br/>
     *
     * - Excludes certain file by <b>hash</b>, or by <b>full URL</b>, or by <b>basename</b>.<br/>
     *   For example, "widgets.js" disables loading all resources with the same name, independently from their paths.
     * - Excludes all resource files, if given '*.js'
     *
     * <b>ATTENTION!</b> This method cannot exclude resources from registered packets, therefore it should be used,
     * when all packets extracted to target array (before script remap or before/after unification of scripts).
     *
     * @param string[] $excludeList List of resource hashes, or file URLs, or their names without paths and slashes.
     */
    protected function excludeJSFiles($excludeList)
    {
        if (in_array('*.js', $excludeList)) {
            $this->scriptFiles = array();
        } else {
            $self = $this;
            $filterFunc = function ($url, $value) use ($self, $excludeList) {
                return $self->_shouldBeLoadedFile($url, $excludeList);
            };
            foreach ($this->scriptFiles as $position => $scriptFiles) {
                $this->scriptFiles[$position] = RSmartLoadHelper::filterByFn($this->scriptFiles[$position], $filterFunc);
            }
        }
    }

    /**
     * Checks, should be loaded given resource file
     *
     * @param string   $resource
     * @param string[] $excludeList
     * @return bool
     */
    private function _shouldBeLoadedFile($resource, $excludeList)
    {
        $possibleEntries = array($resource, $this->_hashString($resource), basename($resource));

        return
            (count(array_intersect($possibleEntries, $this->alwaysReloadableResources)) > 0) || // is "always reloadable"
            (count(array_intersect($possibleEntries, $excludeList)) === 0);          // is not contained in $excludeList
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
            'hashMethod'         => $this->hashMethod,
            'enableLog'          => $this->enableLog,
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

    /**
     * Sets alias of extension path (if not defined manually)
     */
    private function _setExtensionPathAliasIfUndefined()
    {
        if (Yii::getPathOfAlias(self::PATH_ALIAS) === false) {
            Yii::setPathOfAlias(self::PATH_ALIAS, realpath(dirname(__FILE__)));
        }
    }
} 