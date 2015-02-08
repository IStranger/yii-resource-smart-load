# Extension to prevent reloading scripts (on AJAX request)

This extension for yii 1.1.x prevent reload resources (on AJAX request), which already exist on client.

## Requirements

+ PHP 5.4.0+. Theoretically should work on 5.3, but not tested.
+ YiiFramework 1.1.14+

## Features

+ Prevent reload of JS files
+ Prevent reload of JS inline blocks
+ Prevent reload of CSS files
+ Prevent reload of CSS inline blocks
+ Flexible configuration of resources disabling

## Limitations

+ Increases incoming traffic (from client to server), because extension uses cookie and http headers.
This is especially important for sites with a large number of included resource files.
This can be adjusted by changing the hash method: **crc32b** takes less size than **md5** (see options of extension).
In addition it should be remembered that the size of the cookie is limited (in browser).
+ Extension does not work if the user has enabled filtering of http headers (for example, on the corporate proxy),
and browser not to accept cookies. However, we assume the probability of such events is low.

## Installation

+ **Manual installation:** extract files under <code>protected/extensions/yii-resource-smart-load</code>.
+ **Installation via composer:** add to your composer.json file ("require" section) the following line  <code>"istranger/yii-resource-smart-load": "dev-master"</code>
  (see <a href="https://packagist.org/packages/istranger/yii-resource-smart-load">packagist page</a>)
+ Add in config file (by default config/main.php):

```php
// application components
'components' => array(
    // ...
    'clientScript' => array(
        // Path of main class: 
            // -- if installed manually: 
        'class' => 'ext.yii-resource-smart-load.RSmartLoadClientScript', 
            // -- if installed via composer: 
        // 'class' => 'application.vendor.istranger.yii-resource-smart-load.RSmartLoadClientScript',  
                    
        // Hashing method for resource names,
        // see possible values: http://php.net/manual/en/function.hash.php#104987 
        // 'hashMethod' => 'md5', // default = 'crc32b'
        
        // Types of resources, that will be tracked by current extension. 
        // If =null, include all resource types: 
        // array('jsFile', 'cssFile', 'jsInline', 'cssInline')
        // 'resourceTypes' => array('jsFile', 'jsInline'), // default = null

        // Enable log on server and client side (debug mode)
        // 'enableLog' => true, // default = false

        // Activate "smart" disabling of resources on all pages
        // 'activateOnAllPages' => true // default = true
        
        // List of resources, that always should be loaded on client 
        // (by name, hash, or full URL)
        // 'alwaysReloadableResources' => array('jquery.yiiactiveform.js')  // default = array()
    )
    // ...
),
```

## Usage

### Typical use case

By default, this extension disables reloading of all matched resources (js/css files and inline blocks).
That is, each resource on the page will be loaded <b>only once</b>, even if it will later be removed from this page.
Therefore, all JS callbacks **on first load** should be bind to the global containers (for example, document) 
using jQuery-method **.on()**. 
At the subsequent AJAX requests already loaded CSS inline blocks (or CSS files) may be replaced by new content, 
therefore, in case you have problems with CSS styles is recommended to set option:

```php
    'resourceTypes' => array('jsFile', 'jsInline'), // or  => array('jsInline')
```

### Advanced use case

For the analysis of disabled/loaded scripts is convenient to use an option **enableLog**, 
that output useful debug information in browser console: 

```php
    'enableLog' => true, // default = false
```

You can more flexible manage resource loading on certain pages using methods: 

+ **RSmartLoadClientScript::disableAllResources(array $types = null);**
    Disables loading of **all** resources. Calling this method disables loading all resources, 
    even if they will registered after calling this method.
+ **RSmartLoadClientScript::disableLoadedResources(array $types = null);**
    Disables loading of resources, which **already loaded on client**. Calling this method disables loading 
    "client" resources, even if they will registered after calling this method.
    
These methods can be invoked in any actions. The argument **$types** is an array of resource types, 
that can be defined using constants:

```php
    array(
        RSmartLoadClientScript::RESOURCE_TYPE_JS_FILE,      // = 'jsFile'
        RSmartLoadClientScript::RESOURCE_TYPE_JS_INLINE,    // = 'jsInline'
        RSmartLoadClientScript::RESOURCE_TYPE_CSS_FILE,     // = 'cssFile'
        RSmartLoadClientScript::RESOURCE_TYPE_CSS_INLINE,   // = 'cssInline'
    )
```

In addition, you can set **activateOnAllPages = false**, and extension will be disabled on all pages. 
You will need to manually configure disabling of resources on certain pages (with the help of these methods).

Alternatively, you can configure exclusion list of resources:

```php
    alwaysReloadableResources' => array('jquery.yiiactiveform.js')  // default = array()
```

These resources always will be loaded on client. Each resource can be presented: 

+ resource file: as **hash**, or **full URL**, or **basename**.
+ resource inline block: as **hash**, or **resource content**.

The hash of specific resource can be get through browser console in the global object of extension (enableLog == true).
For example:
 
```javascript
yiiResourceSmartLoad.resources = 
  {
    "55eb35cb": {
      "resource": "/test_yii/assets/62fbda6e/jquery.js",
      "hash": "55eb35cb",
      "source": "2015-02-08 23:14:30,\r\nGET,\r\nurl = /test_yii/index.php?r=site/ajaxForm,\r\nreferrer = http://test.dev/test_yii/index.php?r=site/contact"
    },
    "fd425af9": {
      "resource": "/test_yii/assets/62fbda6e/jquery.maskedinput.js",
      "hash": "fd425af9",
      "source": "2015-02-08 23:14:30,\r\nGET,\r\nurl = /test_yii/index.php?r=site/ajaxForm,\r\nreferrer = http://test.dev/test_yii/index.php?r=site/contact"
    },
    "9c7cc7be": {
      "resource": "/test_yii/assets/fbf1b54a/resource_smart_load.js",
      "hash": "9c7cc7be",
      "source": "2015-02-08 23:14:30,\r\nGET,\r\nurl = /test_yii/index.php?r=site/ajaxForm,\r\nreferrer = http://test.dev/test_yii/index.php?r=site/contact"
    },
    "ecd7b644": {
      "resource": "/test_yii/assets/62fbda6e/jui/js/jquery-ui.min.js",
      "hash": "ecd7b644",
      "source": "2015-02-08 23:14:30,\r\nGET,\r\nurl = /test_yii/index.php?r=site/ajaxForm,\r\nreferrer = http://test.dev/test_yii/index.php?r=site/contact"
    },
    "5e030b8c": {
      "resource": "jQuery('#yw0').tabs({'collapsible':true});",
      "hash": "5e030b8c",
      "source": "2015-02-08 23:14:30,\r\nGET,\r\nurl = /test_yii/index.php?r=site/ajaxForm,\r\nreferrer = http://test.dev/test_yii/index.php?r=site/contact"
    },
    "5ce96349": {
      "resource": "(function ($) {   // yii-resource-smart-load extension\r\nwindow.yiiResourceSmartLoad.initExtension({\"...",
      "hash": "5ce96349",
      "source": "2015-02-08 23:14:30,\r\nGET,\r\nurl = /test_yii/index.php?r=site/ajaxForm,\r\nreferrer = http://test.dev/test_yii/index.php?r=site/contact"
    }
  }
```


## Similar extensions

To prevent reloading scripts you can use <a href="https://github.com/nlac/nlsclientscript" target="_blank">nlsclientscript</a>

However, there are a few differences:

* Extension used different algorithm: at AJAX request duplicated resource files are deleted on the client <b>after</b>
receiving the content (not on the server, as in our realization). We assume that our approach is conceptually more correct.

* Our realization don't deletes (intentionally) the resources, which included directly in html code
(without registering through ClientScript). In this case, we assume that these resources are very necessary.

* nlsclientscript don't deletes duplicate CSS files. But there is nothing wrong, because most browsers will not re-load
the files.
