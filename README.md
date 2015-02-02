# Extension for prevent reloading scripts (on AJAX request)

This extension for yii 1.1.x prevent reload resources, which already exist on client.

## Requirements

+ PHP 5.4.0+. Theoretically should work on 5.3, but not tested.
+ YiiFramework 1.1.14+


Note:

    The extension is under active development and requires a good of refactoring (especially in the client area).
    But before use on production version of your website, you must carefully test it (the tests will come later).
    It is also planned deep change of algorithm that eliminates many of the limitations of this extension (see below).
    Therefore it is possible instead of refactoring most of the code will be deleted.

## Features

+ Prevent reload of js-script files
+ Prevent reload of css-style files (and other link files like *.ico)

## Limitations

+ Cannot prevent reload inline blocks of js/css (try to eliminate in the new version).
+ Increases the traffic between client and server (extension uses cookie and http headers).
This is especially important for sites with a large number of included resource files.
This can be adjusted by changing the hash method: <b>crc32b</b> takes less size than <b>md5</b> (see option of extension).
+ Extension does not work if the user has enabled filtering of http headers (for example, on company's proxy),
and browser not to accept cookies. However, we believe the probability of such events is low.

## Usage

+ Extract files under <code> protected/extensions/resourcesmartload</code>.
+ Add in config file (by default config/main.php):

```php
// application components
'components' => array(
    // ...
    'clientScript' => array(
        'class' => 'ext.resourcesmartload.RSmartLoadClientScript',

        // Hashing method for resource names ('crc32b' or 'md5')
        // 'hashMethod' => 'crc32b', // default = 'md5'

        // Enable log on server and client side (debug mode)
        // 'enableLog' => true, // default = false

        // Activate "smart" disabling of resources on all pages
        // 'activateOnAllPages' => true // default = true
    )
    // ...
),
```


## Similar extensions

To prevent reloading scripts you can use <a href="https://github.com/nlac/nlsclientscript">nlsclientscript</a>

However, there are a few differences:

* Extension used different algorithm: at AJAX request duplicated resource files are deleted on the client <b>after</b>
receiving the content (not on the server, as in our realization). We assume that our approach is conceptually more correct.

* Our realization don't deletes (intentionally) the resources, which included directly in html code
(without registering through ClientScript). In this case, we assume that these resources are very necessary.

* nlsclientscript don't deletes duplicate CSS files. But there is nothing wrong, because most browsers will not re-load
the files.
