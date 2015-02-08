<?php

/**
 * RSmartLoadClientScript class file.
 *
 * @author  G.Azamat <m@fx4web.com>
 * @link    http://fx4.ru/
 * @link    https://github.com/IStranger/yii-resource-smart-load
 * @version 0.2 (2015-02-08)
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

    /**
     * Event name, that raised immediate before rendering of all resources, see {@link afterUnifyScripts}
     */
    const EVENT_AFTER_UNIFY_SCRIPTS = 'onAfterUnifyScripts';

    const RESOURCE_TYPE_JS_FILE = 'jsFile';
    const RESOURCE_TYPE_JS_INLINE = 'jsInline';
    const RESOURCE_TYPE_CSS_FILE = 'cssFile';
    const RESOURCE_TYPE_CSS_INLINE = 'cssInline';

    /**
     * @var string[] Possible types of resources
     */
    static $resourceTypesAll = array(
        self::RESOURCE_TYPE_JS_FILE,
        self::RESOURCE_TYPE_JS_INLINE,
        self::RESOURCE_TYPE_CSS_FILE,
        self::RESOURCE_TYPE_CSS_INLINE,
    );


    /**
     * @var string Current hashing method (for resource names).
     *             Possible values: see {@link hash_algos} and {@link http://php.net/manual/en/function.hash.php#104987}
     */
    public $hashMethod = 'crc32b';

    /**
     * @var string[] Types of resources, that will be tracked by current extension.
     * By default (=null), include all resource types {@link $resourceTypesAll}.
     */
    public $resourceTypes = null;

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

    private $_jsCount = 0;

    public function init()
    {
        $this->_setExtensionPathAliasIfUndefined();
        Yii::import(self::PATH_ALIAS . '.*');

        $this->_publishExtensionResources();

        if ($this->activateOnAllPages) {
            $this->disableLoadedResources($this->resourceTypes);
        }
    }

    protected function unifyScripts()
    {
        parent::unifyScripts();
        $this->afterUnifyScripts(); // raise our event

        // after all manipulations and filtration of registered resources
        $this->_publishExtensionClientInit();   // this js code should be included on each AJAX request
        $this->_publishRegisteredResourcesUpdater();
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
     * @param string[] $types Types of resources, that should be disabled.
     *                        Possible values see {@link resourceTypesAll}.
     *
     * @see RSmartLoadClientScript::disableLoadedResources
     */
    public function disableAllResources(array $types = null)
    {
        $self = $this;
        $types = $types ?: static::$resourceTypesAll;
        $this->attachEventHandler(self::EVENT_AFTER_UNIFY_SCRIPTS, function ($event) use ($self, $types) {
            in_array($self::RESOURCE_TYPE_JS_FILE, $types) && $self->excludeJSFiles(array('*'));
            in_array($self::RESOURCE_TYPE_JS_INLINE, $types) && $self->excludeJSInline(array('*'));
            in_array($self::RESOURCE_TYPE_CSS_FILE, $types) && $self->excludeCSSFiles(array('*'));
            in_array($self::RESOURCE_TYPE_CSS_INLINE, $types) && $self->excludeCSSInline(array('*'));
        });
    }

    /**
     * Disables loading of resource files, which already loaded on client. <br/>
     * Used at AJAX requests. List of resource hashes obtained from "client" variable {@link Request::getClientVar}.
     *
     * <b>ATTENTION!</b> Calling this method disables loading <u><b>"client"</b></u> resources,
     * even if they will registered after calling this method.
     *
     * @param string[] $types Types of resources, that should be disabled.
     *                        Possible values see {@link resourceTypesAll}.
     *
     * @see RSmartLoadClientScript::disableAllResources
     */
    public function disableLoadedResources(array $types = null)
    {
        $self = $this;
        $types = $types ?: static::$resourceTypesAll;
        $this->attachEventHandler(self::EVENT_AFTER_UNIFY_SCRIPTS, function ($event) use ($self, $types) {
            $hashList = $self->getLoadedResourcesHashes();
            in_array($self::RESOURCE_TYPE_JS_FILE, $types) && $self->excludeJSFiles($hashList);
            in_array($self::RESOURCE_TYPE_JS_INLINE, $types) && $self->excludeJSInline($hashList);
            in_array($self::RESOURCE_TYPE_CSS_FILE, $types) && $self->excludeCSSFiles($hashList);
            in_array($self::RESOURCE_TYPE_CSS_INLINE, $types) && $self->excludeCSSInline($hashList);
        });
    }


    /**
     * Excludes from array {@link cssFiles} (registered CSS files) given files of resources. <br/>
     *
     * - Excludes certain file by <b>hash</b>, or <b>full URL</b>, or <b>basename</b>.<br/>
     *   For example, "widgets.css" disables loading all resources with the same name, independently from their paths.
     * - Excludes all resource files, if given '*'
     *
     * <b>ATTENTION!</b> This method cannot exclude resources from registered packets, therefore it should be used,
     * when all packets extracted to target array (after script remap or before/after unification of scripts).
     *
     * @param string[] $excludeList List of resource hashes, or file URLs, or their names without paths and slashes.
     */
    protected function excludeCSSFiles(array $excludeList)
    {
        if (in_array('*', $excludeList)) {
            $this->cssFiles = array();
        } else {
            $self = $this;
            $filterFunc = function ($url, $value) use ($self, $excludeList) {
                return $self->shouldBeLoadedFile($url, $excludeList);
            };
            $this->cssFiles = RSmartLoadHelper::filterByFn($this->cssFiles, $filterFunc);
        }
    }

    /**
     * Excludes from array {@link scriptFiles} (registered JS files) given files of resources. <br/>
     *
     * - Excludes certain file by <b>hash</b>, or <b>full URL</b>, or <b>basename</b>.<br/>
     *   For example, "widgets.js" disables loading all resources with the same name, independently from their paths.
     * - Excludes all resource files, if given '*'
     *
     * <b>ATTENTION!</b> This method cannot exclude resources from registered packets, therefore it should be used,
     * when all packets extracted to target array (after script remap or before/after unification of scripts).
     *
     * @param string[] $excludeList List of resource hashes, or file URLs, or their names without paths and slashes.
     */
    protected function excludeJSFiles(array $excludeList)
    {
        if (in_array('*', $excludeList)) {
            $this->scriptFiles = array();
        } else {
            $self = $this;
            $filterFunc = function ($url, $value) use ($self, $excludeList) {
                return $self->shouldBeLoadedFile($url, $excludeList);
            };
            foreach ($this->scriptFiles as $position => $scriptFiles) {
                $this->scriptFiles[$position] = RSmartLoadHelper::filterByFn($this->scriptFiles[$position], $filterFunc);
            }
        }
    }

    /**
     * Excludes from array {@link css} (registered inline CSS blocks) given resources.
     *
     * - Excludes certain inline CSS block by <b>hash</b>, or <b>content</b> (see {@link _extractCssContent}).<br/>
     * - Excludes all CSS blocks, if given '*'
     *
     * @param string[] $excludeList List of resource hashes, or content
     */
    protected function excludeCSSInline(array $excludeList)
    {
        if (in_array('*', $excludeList)) {
            $this->css = array();
        } else {
            $self = $this;
            $filterFunc = function ($key, $cssParts) use ($self, $excludeList) {        // join css content + media type
                return $self->shouldBeLoadedContent($self->_extractCssContent($cssParts), $excludeList);
            };
            $this->css = RSmartLoadHelper::filterByFn($this->css, $filterFunc);
        }
    }

    /**
     * Excludes from array {@link scripts} (registered inline JS blocks) given resources.
     *
     * - Excludes certain inline JS block by <b>hash</b>, or <b>content</b>.<br/>
     * - Excludes all JS blocks, if given '*'
     *
     * @param string[] $excludeList List of resource hashes, or content
     */
    protected function excludeJSInline(array $excludeList)
    {
        if (in_array('*', $excludeList)) {
            $this->scripts = array();
        } else {
            $self = $this;
            $filterFunc = function ($key, $content) use ($self, $excludeList) {
                return $self->shouldBeLoadedContent($content, $excludeList);
            };
            foreach ($this->scripts as $position => $scriptBlocks) {
                $this->scripts[$position] = RSmartLoadHelper::filterByFn($this->scripts[$position], $filterFunc);
            }
        }
    }

    /**
     * Returns plain array of all registered resources (for resource file - URL, for inline blocks - content).
     *
     * <b>ATTENTION!</b> This method don't include resources from registered packets, therefore it should be used,
     * when all packets extracted to {@link cssFiles} and {@link scriptFiles} arrays
     * (after script remap or before/after unification of scripts).
     *
     * @param string[] $types Types of {resources}, see {@link resourceTypesAll}
     *
     * @return string[]
     */
    protected function getRegisteredResourcesList(array $types = null)
    {
        $types = $types ?: static::$resourceTypesAll;
        $resultList = array();

        // JS files
        if (in_array(self::RESOURCE_TYPE_JS_FILE, $types)) {
            foreach ($this->scriptFiles as $position => $filesGroup) {
                $resultList = array_merge($resultList, array_values($filesGroup));
            }
        }

        // CSS files
        if (in_array(self::RESOURCE_TYPE_CSS_FILE, $types)) {
            $resultList = array_merge($resultList, array_keys($this->cssFiles)); // Unlike JS-scripts for CSS does not specify the position of including.
        }

        // CSS inline
        if (in_array(self::RESOURCE_TYPE_CSS_INLINE, $types)) {
            $self = $this;
            $cssBlocks = RSmartLoadHelper::createByFn($this->css, function ($key, $cssParts) use ($self) {
                return array($key, $self->_extractCssContent($cssParts));
            });
            $resultList = array_merge($resultList, array_values($cssBlocks));
        }

        // JS inline
        if (in_array(self::RESOURCE_TYPE_JS_INLINE, $types)) {
            foreach ($this->scripts as $position => $filesGroup) {
                $resultList = array_merge($resultList, array_values($filesGroup));
            }
        }

        return $resultList;
    }

    /**
     * Checks, should be loaded given resource file
     *
     * @param string   $resource    Full URL, basename, or hash of resource
     * @param string[] $excludeList List of resources, that should be excluded
     * @return bool
     */
    protected function shouldBeLoadedFile($resource, array $excludeList)
    {
        $possibleEntries = array($resource, $this->hashString($resource), basename($resource));

        return
            (count(array_intersect($possibleEntries, $this->alwaysReloadableResources)) > 0) || // is "always reloadable"
            (count(array_intersect($possibleEntries, $excludeList)) === 0);          // is not contained in $excludeList
    }

    /**
     * Checks, should be loaded given resource content
     *
     * @param string   $resource    Content, or hash of resource
     * @param string[] $excludeList List of resources, that should be excluded
     * @return bool
     */
    protected function shouldBeLoadedContent($resource, array $excludeList)
    {
        $possibleEntries = array($resource, $this->hashString($resource));

        return
            (count(array_intersect($possibleEntries, $this->alwaysReloadableResources)) > 0) || // is "always reloadable"
            (count(array_intersect($possibleEntries, $excludeList)) === 0);          // is not contained in $excludeList
    }

    /**
     * Hash of given string with current hash method {@link hashMethod}
     *
     * @param string $str String, that will be hashed
     * @return string       Hashed string
     */
    protected function hashString($str)
    {
        return hash($this->hashMethod, $str);
    }

    /**
     * Returns prepared data on registered resources, and current request.
     * If options {@link enableLog} is TRUE, method include extended comment data.
     *
     * @return array Array in format: [ ['resource' => ..., 'hash' => ..., 'comment' =>  ], ... ]
     */
    private function _prepareDataOnRegisteredResources()
    {
        // data on current request
        $self = $this;
        $requestInfo = Yii::app()->request->requestType . (Yii::app()->request->isAjaxRequest ? '/AJAX' : '');
        $comment = $this->enableLog
            ? array(
                date('Y-m-d H:i:s'),
                $requestInfo,
                'url = ' . Yii::app()->request->url,
                'referrer = ' . (Yii::app()->request->urlReferrer ?: '')
            )
            : array($requestInfo);

        // data on registered resources
        $trackedResources = $this->getRegisteredResourcesList($this->resourceTypes);
        return RSmartLoadHelper::createByFn($trackedResources,
            function ($key, $resource) use ($self, $comment) {
                return array($key, array(
                    'resource' => $self->_limitStr($resource, 100),
                    'hash'     => $self->hashString($resource),
                    'comment'  => join(',' . PHP_EOL, $comment)
                ));
            });
    }

    /**
     * Returns string, that will be unique for given css inline block
     *
     * @param string[] $cssParts Parts of css inline {block}, that used in {@link css}. <br/>
     *                              $cssParts[0] - css content, $cssParts[1] - media type
     * @return string               Full css block content
     */
    private function _extractCssContent(array $cssParts)
    {
        $mediaType = $cssParts[1];
        $cssContent = $cssParts[0];

        return $mediaType . '|' . $cssContent;
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
     * Registers client resources of this extension and corresponding scripts
     */
    private function _publishExtensionResources()
    {
        $this->registerCoreScript('jquery');

        // Initialization of extension resources
        $assetsExt = dirname(__FILE__) . '/' . 'assets';
        $assetsPath = Yii::app()->getAssetManager()->publish($assetsExt);
        $this->registerScriptFile($assetsPath . '/' . 'resource_smart_load.js');
    }

    /**
     * Registers client script for initialization of client side (+ extension options export to client side)
     */
    private function _publishExtensionClientInit()
    {
        $extensionOptionsJson = json_encode(array(
            'hashMethod'                => $this->hashMethod,
            'resourceTypes'             => $this->resourceTypes,
            'enableLog'                 => $this->enableLog,
            'activateOnAllPages'        => $this->activateOnAllPages,
            'alwaysReloadableResources' => $this->alwaysReloadableResources,
        ));
        $this->_publishExtensionJs('%extensionObject%.initExtension(%optionsJson%); ', array(
            '%extensionObject%' => self::JS_GLOBAL_OBJ_PATH,
            '%optionsJson%'     => $extensionOptionsJson,
        ));
    }

    /**
     * Publish script, that extended (on client) list of loaded resources
     */
    private function _publishRegisteredResourcesUpdater()
    {
        $resourcesJson = json_encode($this->_prepareDataOnRegisteredResources());

        $this->_publishExtensionJs(
            array(
                '$(function () {',
                '    var app = %extensionObject%;',
                '    var resources = %resourcesJson%;',
                '    resources.map(function (dataObj) { ',
                '        app.addResource(dataObj.hash, dataObj.resource, dataObj.comment); ',
                '    });',
                '});',
            ),
            array(
                '%extensionObject%' => self::JS_GLOBAL_OBJ_PATH,
                '%resourcesJson%'   => $resourcesJson,
            )
        );
    }

    /**
     * Wraps in js-callback and registers given js code.
     * Used <b>only</b> for publication of scripts of <b>current extension</b>
     *
     * @param string|string[] $jsScriptLines Lines (or single line) of js code (will be joined EOL)
     * @param array           $replaceParams Params, that will be replaced in js code. Format: ['%from%' => 'to']
     * @param int             $position      Position of the {js} code (see {@link CClientScript::registerScript})
     */
    private function _publishExtensionJs($jsScriptLines, $replaceParams = array(), $position = CClientScript::POS_HEAD)
    {
        if (!is_array($jsScriptLines)) {
            $jsScriptLines = array($jsScriptLines);
        }
        array_unshift($jsScriptLines, '(function ($) {   // yii-resource-smart-load extension');
        array_push($jsScriptLines, '})(jQuery);');
        $scriptCode = join(PHP_EOL, $jsScriptLines);
        $scriptCode = strtr($scriptCode, $replaceParams);

        $this->registerScript($this->_generateUniqueStr(), $scriptCode, $position);
        $this->_jsCount++;
    }

    /**
     * Generates more unique string through internal counter, because function {@link uniqid} returns
     * sometimes identical strings
     *
     * @return string unique string
     */
    private function _generateUniqueStr()
    {
        return uniqid($this->_jsCount . '_', true);
    }

    private function _limitStr($str, $maxLength = 100)
    {
        return mb_substr($str, 0, $maxLength) . ((mb_strlen($str) > $maxLength) ? '...' : '');
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