;
(function ($) {
    var resourceSmartLoad = {

        HASH_METHOD_CRC32: 'crc32b',
        HASH_METHOD_MD5  : 'md5',

        /**
         * Is already initialized (for prevent repeated initializing)
         */
        isInitialized: false,

        /**
         * Contain common function, helpers
         */
        fn: {},


        /**
         * Options of extension (will be exported from server side).
         * Null values will be replaced after initialization (on client).
         *
         * Contain following options:
         * <ul>
         *     <li>{String} {@link extensionOptions.hashMethod}</li>
         *     <li>{Boolean} {@link extensionOptions.enableLog}</li>
         *     <li>{Boolean} {@link extensionOptions.activateOnAllPages}</li>
         * </ul>
         *
         * @type {Object}
         */
        extensionOptions: {
            /**
             * Current hashing method (for resource names)
             * @type {String}
             */
            hashMethod        : null,
            /**
             * Enable log of registered/disabled resources
             * @type {Boolean}
             */
            enableLog         : null,
            /**
             * Activates "smart" disabling of resources on all pages
             * @type {Boolean}
             */
            activateOnAllPages: null
        },

        /**
         * Resources, that already loaded on client
         *
         * @type {Array}
         * @see getResourceByHash
         * @see getResourcesHashList
         * @see addResource
         */
        resources: {},

        /**
         * Returns loaded resource with given hash
         *
         * @param {String} hash    Hash of resource
         * @returns {String|null}  Resource identifier (for js/css files: name/url of file). Returns null if not found
         * @see resources
         */
        getResourceByHash: function (hash) {
            var app = this;

            return app.resources[hash] ? app.resources[hash] : null;
        },

        /**
         * Returns list of hashes of all loaded resources
         *
         * @return {String[]} List of hashes
         * @see resources
         */
        getResourcesHashList: function () {
            var app = this;

            var result = [];
            $.each(app.resources, function (hash, resource) {
                result.push(resource.hash);
            });
            return result;
        },

        /**
         * Adds given resource to list of loaded resources {@link resources}
         *
         * @param {String} resource     Resource identifier (for js/css files: name/url of file).
         * @param {String} [comment]    Description for internal using (on debug mode)
         * @see resources
         */
        addResource: function (resource, comment) {
            var app = this;
            var hash = app.hashString(resource);
            var isAlreadyLoaded = Boolean(app.getResourceByHash(hash));

            if (!isAlreadyLoaded) {
                app.resources[hash] = {
                    resource: resource,
                    hash    : hash,
                    source  : comment
                };
            }

            app.log('addResource = ', resource, !isAlreadyLoaded ? ' ...added' : ' ...skipped');
        },

        /**
         * Initializes extension
         *
         * @param {Object} extensionOptionsInit   Options, that exported from server side, see {@link resourceSmartLoad.extensionOptions}
         * @see resourceSmartLoad.extensionOptions
         */
        initExtension: function (extensionOptionsInit) {
            var app = this;

            if (!app.isInitialized) {
                app.extensionOptions = extensionOptionsInit;

                $(function () {
                    // find all resources on page
                    $.each(app.getLoadedResources(true), function (key, element) {
                        app.addResource(element, 'from main page');
                    })

                    // update of "app.resources" after all ajax requests
                    $.ajaxSetup({
                        global    : true,
                        dataFilter: function (data, type) { // console.dir({ arguments: arguments, self: this });
                            if (!type || (type == "html") || (type == "text")) {
                                var matches;

                                var regExps = {
                                    // script files (extracted attribute "src")
                                    regExpScriptFiles: /\<script[^\<\>]*src[\s]*=[\s]*(?:"|')([^\<\>"']+)(?:"|')[^\<\>]*\>/g,
                                    // CSS, fonts, and other link resources (extracted attribute "href")
                                    regExpLinkFiles  : /\<link[^\<\>]*href[\s]*=[\s]*(?:"|')([^\<\>"']+)(?:"|')[^\<\>]*\>/g
                                };

                                $.each(regExps, function (key, regExp) {
                                    while (matches = regExp.exec(data)) {
                                        var filePath = matches[1];

                                        app.addResource(filePath, 'from ajax request'); // console.log('added filePath = ', filePath);
                                    }
                                });
                            }
                            return data;
                        }
                    });

                    // hook for all ajax-requests, in "client" variable we send hashes of all loaded resources
                    $(document).ajaxSend(function (event, jqXHR, settings) {
                        app.setClientVar(jqXHR, {resourcesList: JSON.stringify(app.getResourcesHashList())}, settings.url);
                        // console.log('ajaxSend >> app.resources, app.getLoadedResources = ', app.resources, app.getLoadedResources(true));
                    });
                });
                app.isInitialized = true;
            }
        },

        /**
         * Returns list of resources (js/css files/styles, icons), which included in DOM
         * (taking into account dynamic loaded files).
         *
         * @param {Boolean}   [onlyFiles=false]     If =true, method returns only files.
         *                                          Inline js/css blocks will be ignored
         * @param {String[]}  [types=['js','css']]  Resource types.
         *                                          By default method returns js-scripts and css-styles (*.ico files too)
         * @return {String[]}                       List of resources.
         *                                          For files used URL, for inline blocks - js/css content
         */
        getLoadedResources: function (onlyFiles, types) {
            onlyFiles = (onlyFiles === undefined) ? false : onlyFiles;
            types = (types === undefined) ? ['js', 'css'] : types;

            var resourcesList = [];
            var fnEach = function (srcAttr) {
                var $this = $(this),
                    src = $this.attr(srcAttr);
                if (src) {
                    resourcesList.push(src);
                } else {
                    if (!onlyFiles) {
                        resourcesList.push($this.text().trim());
                    }
                }
            };
            if (types.indexOf('js') >= 0) {
                $('script').each(function () {
                    fnEach.apply(this, ['src']);
                });
            }
            if (types.indexOf('css') >= 0) {
                $('link, style[type="text/css"]').each(function () {
                    fnEach.apply(this, ['href']);
                });
            }
            return resourcesList;
        },

        /**
         * Sets "client" variable for given AJAX request (inserts variable into HTTP headers and cookie).
         * Cookie will be deleted after complete of given request.
         * On server this variable can be accessed through RSmartLoadHelper::getClientVar(name).
         *
         * @param {jQuery.jqXHR}    jqXHR       jQuery.ajax-object of request (returns by function $.ajax,
         *                                      or passed as argument in handlers $.ajax.beforeSend, $.ajaxSend etc.).
         * @param {Object}          vars        Object with variables in format {name:value}.
         *                                      "value" cannot contain non english symbols.
         * @param {String}          [url='/']   URL, which will be sent to ajax-request
         *                                      (for this URL will be assigned a cookie).
         * @see http://api.jquery.com/jQuery.ajax/#jqXHR
         * @see http://api.jquery.com/jQuery.ajax/#jQuery-ajax-settings
         */
        setClientVar: function (jqXHR, vars, url) {
            var app = this;
            var prefix = 'clientvar';  // Should not contain extraneous characters, otherwise the cookie names/titles can be invalid
            url = url || '/';

            $.each(vars, function (key, value) {
                jqXHR.setRequestHeader(prefix + key, value);
                app.fn.setCookie(prefix + key, value, {path: url});
            });
            jqXHR.always(function () {
                $.each(vars, function (key, value) {
                    app.fn.deleteCookie(prefix + key, url);
                });
            });
        },

        /**
         * Hashes given string using current hash method {@link hashMethod}
         *
         * @param {String}  $str    String
         * @return {String}         Hash of string
         */
        hashString: function (str) {
            var app = this;
            var method = app.extensionOptions.hashMethod;

            switch (method) {
                case app.HASH_METHOD_CRC32:
                    return app.fn.hashCrc32Hex(str);
                case app.HASH_METHOD_MD5:
                    return app.fn.hashMd5(str);
                default :
                    throw 'Undefined hash method: ' + method;
            }

        },

        /**
         * Adds to log given strings/variables
         *
         * @param {...*}    Variable number of parameters
         */
        log: function () {
            var app = this;

            if (app.extensionOptions.enableLog) {
                var args = ['ResourceSmartLoad >>  '];
                $.each(arguments, function (key, val) { // copy values from "pseudo array" to normal array
                    args.push(val);
                });
                console.log.apply(this, args);
            }
        }
    };

    resourceSmartLoad.fn = {
        /**
         * Returns cookie by name.
         *
         * @param {String}  name    Cookie name
         * @returns {String}        Cookie value. If not found, returns undefined
         */
        getCookie: function (name) {
            var matches = document.cookie.match(new RegExp(
                "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
            ));
            return matches ? decodeURIComponent(matches[1]) : undefined;
        },

        /**
         * Sets cookie.
         *
         * @param {String} name         Cookie name
         * @param {String} value        Cookie value
         * @param {Object} [options={}] Addtitional options:
         * <ul>
         *     <li>
         *         <b>expires</b> - Expiry time of cookie. Is interpreted differently, depending on the type of:
         *         <ul>
         *             <li>{Number} — number of seconds. For example, expires: 3600 - Cookie for an hour.</li>
         *             <li>{Date} — expiration date.</li>
         *             <li>If expires in the past, the cookie will be deleted.</li>
         *             <li>If expires is undefined or 0, the cookie will be set as a session and will disappear
         *             when the browser is closed.</li>
         *         </ul>
         *     </li>
         *     <li><b>path</b> - Cookie path.</li>
         *     <li><b>domain</b> - Cookie domain.</li>
         *     <li><b>secure</b> - If =true, cookie will be sent over a secure connection.</li>
         * </ul>
         */
        setCookie: function (name, value, options) {
            options = options || {};

            var expires = options.expires;

            if (typeof expires == "number" && expires) {
                var d = new Date();
                d.setTime(d.getTime() + expires * 1000);
                expires = options.expires = d;
            }
            if (expires && expires.toUTCString) {
                options.expires = expires.toUTCString();
            }

            value = encodeURIComponent(value);

            var updatedCookie = name + "=" + value;

            for (var propName in options) {
                updatedCookie += "; " + propName;
                var propValue = options[propName];
                if (propValue !== true) {
                    updatedCookie += "=" + propValue;
                }
            }

            document.cookie = updatedCookie;
        },

        /**
         * Deletes cookie by name (for given path).
         * If the cookie is set on a certain path, one name is not enough to read/delete.
         *
         * @param {String} name         Cookie name
         * @param {String} [url]        URL for cookie
         */
        deleteCookie: function (name, url) {
            var fn = this;
            var value = {expires: -1};  // "Cookie expired"
            if (url) {
                $.extend(value, {path: url});
            }
            fn.setCookie(name, "", value);
        },

        /**
         * Hashes given string using the MD5 algorithm
         * ATTENTION! Result depends on the encoding of the original string (must be utf8)!
         *
         * @param {String}    str   String
         * @returns {String}        Hashed string
         * @see http://phpjs.org/functions/md5/
         */
        hashMd5: function (str) {
            var xl;

            var rotateLeft = function (lValue, iShiftBits) {
                return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
            };
            var addUnsigned = function (lX, lY) {
                var lX4, lY4, lX8, lY8, lResult;
                lX8 = (lX & 0x80000000);
                lY8 = (lY & 0x80000000);
                lX4 = (lX & 0x40000000);
                lY4 = (lY & 0x40000000);
                lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
                if (lX4 & lY4) {
                    return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
                }
                if (lX4 | lY4) {
                    if (lResult & 0x40000000) {
                        return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
                    } else {
                        return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
                    }
                } else {
                    return (lResult ^ lX8 ^ lY8);
                }
            };
            var _F = function (x, y, z) {
                return (x & y) | ((~x) & z);
            };
            var _G = function (x, y, z) {
                return (x & z) | (y & (~z));
            };
            var _H = function (x, y, z) {
                return (x ^ y ^ z);
            };
            var _I = function (x, y, z) {
                return (y ^ (x | (~z)));
            };
            var _FF = function (a, b, c, d, x, s, ac) {
                a = addUnsigned(a, addUnsigned(addUnsigned(_F(b, c, d), x), ac));
                return addUnsigned(rotateLeft(a, s), b);
            };
            var _GG = function (a, b, c, d, x, s, ac) {
                a = addUnsigned(a, addUnsigned(addUnsigned(_G(b, c, d), x), ac));
                return addUnsigned(rotateLeft(a, s), b);
            };
            var _HH = function (a, b, c, d, x, s, ac) {
                a = addUnsigned(a, addUnsigned(addUnsigned(_H(b, c, d), x), ac));
                return addUnsigned(rotateLeft(a, s), b);
            };
            var _II = function (a, b, c, d, x, s, ac) {
                a = addUnsigned(a, addUnsigned(addUnsigned(_I(b, c, d), x), ac));
                return addUnsigned(rotateLeft(a, s), b);
            };
            var convertToWordArray = function (str) {
                var lWordCount;
                var lMessageLength = str.length;
                var lNumberOfWords_temp1 = lMessageLength + 8;
                var lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64;
                var lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16;
                var lWordArray = new Array(lNumberOfWords - 1);
                var lBytePosition = 0;
                var lByteCount = 0;
                while (lByteCount < lMessageLength) {
                    lWordCount = (lByteCount - (lByteCount % 4)) / 4;
                    lBytePosition = (lByteCount % 4) * 8;
                    lWordArray[lWordCount] = (lWordArray[lWordCount] | (str.charCodeAt(lByteCount) << lBytePosition));
                    lByteCount++;
                }
                lWordCount = (lByteCount - (lByteCount % 4)) / 4;
                lBytePosition = (lByteCount % 4) * 8;
                lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition);
                lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
                lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
                return lWordArray;
            };
            var wordToHex = function (lValue) {
                var wordToHexValue = '',
                    wordToHexValue_temp = '',
                    lByte, lCount;
                for (lCount = 0; lCount <= 3; lCount++) {
                    lByte = (lValue >>> (lCount * 8)) & 255;
                    wordToHexValue_temp = '0' + lByte.toString(16);
                    wordToHexValue = wordToHexValue + wordToHexValue_temp.substr(wordToHexValue_temp.length - 2, 2);
                }
                return wordToHexValue;
            };
            var x = [],
                k, AA, BB, CC, DD, a, b, c, d, S11 = 7,
                S12 = 12,
                S13 = 17,
                S14 = 22,
                S21 = 5,
                S22 = 9,
                S23 = 14,
                S24 = 20,
                S31 = 4,
                S32 = 11,
                S33 = 16,
                S34 = 23,
                S41 = 6,
                S42 = 10,
                S43 = 15,
                S44 = 21;

            // str = this.utf8_encode(str);     // UTF8 disabled
            x = convertToWordArray(str);
            a = 0x67452301;
            b = 0xEFCDAB89;
            c = 0x98BADCFE;
            d = 0x10325476;

            xl = x.length;
            for (k = 0; k < xl; k += 16) {
                AA = a;
                BB = b;
                CC = c;
                DD = d;
                a = _FF(a, b, c, d, x[k + 0], S11, 0xD76AA478);
                d = _FF(d, a, b, c, x[k + 1], S12, 0xE8C7B756);
                c = _FF(c, d, a, b, x[k + 2], S13, 0x242070DB);
                b = _FF(b, c, d, a, x[k + 3], S14, 0xC1BDCEEE);
                a = _FF(a, b, c, d, x[k + 4], S11, 0xF57C0FAF);
                d = _FF(d, a, b, c, x[k + 5], S12, 0x4787C62A);
                c = _FF(c, d, a, b, x[k + 6], S13, 0xA8304613);
                b = _FF(b, c, d, a, x[k + 7], S14, 0xFD469501);
                a = _FF(a, b, c, d, x[k + 8], S11, 0x698098D8);
                d = _FF(d, a, b, c, x[k + 9], S12, 0x8B44F7AF);
                c = _FF(c, d, a, b, x[k + 10], S13, 0xFFFF5BB1);
                b = _FF(b, c, d, a, x[k + 11], S14, 0x895CD7BE);
                a = _FF(a, b, c, d, x[k + 12], S11, 0x6B901122);
                d = _FF(d, a, b, c, x[k + 13], S12, 0xFD987193);
                c = _FF(c, d, a, b, x[k + 14], S13, 0xA679438E);
                b = _FF(b, c, d, a, x[k + 15], S14, 0x49B40821);
                a = _GG(a, b, c, d, x[k + 1], S21, 0xF61E2562);
                d = _GG(d, a, b, c, x[k + 6], S22, 0xC040B340);
                c = _GG(c, d, a, b, x[k + 11], S23, 0x265E5A51);
                b = _GG(b, c, d, a, x[k + 0], S24, 0xE9B6C7AA);
                a = _GG(a, b, c, d, x[k + 5], S21, 0xD62F105D);
                d = _GG(d, a, b, c, x[k + 10], S22, 0x2441453);
                c = _GG(c, d, a, b, x[k + 15], S23, 0xD8A1E681);
                b = _GG(b, c, d, a, x[k + 4], S24, 0xE7D3FBC8);
                a = _GG(a, b, c, d, x[k + 9], S21, 0x21E1CDE6);
                d = _GG(d, a, b, c, x[k + 14], S22, 0xC33707D6);
                c = _GG(c, d, a, b, x[k + 3], S23, 0xF4D50D87);
                b = _GG(b, c, d, a, x[k + 8], S24, 0x455A14ED);
                a = _GG(a, b, c, d, x[k + 13], S21, 0xA9E3E905);
                d = _GG(d, a, b, c, x[k + 2], S22, 0xFCEFA3F8);
                c = _GG(c, d, a, b, x[k + 7], S23, 0x676F02D9);
                b = _GG(b, c, d, a, x[k + 12], S24, 0x8D2A4C8A);
                a = _HH(a, b, c, d, x[k + 5], S31, 0xFFFA3942);
                d = _HH(d, a, b, c, x[k + 8], S32, 0x8771F681);
                c = _HH(c, d, a, b, x[k + 11], S33, 0x6D9D6122);
                b = _HH(b, c, d, a, x[k + 14], S34, 0xFDE5380C);
                a = _HH(a, b, c, d, x[k + 1], S31, 0xA4BEEA44);
                d = _HH(d, a, b, c, x[k + 4], S32, 0x4BDECFA9);
                c = _HH(c, d, a, b, x[k + 7], S33, 0xF6BB4B60);
                b = _HH(b, c, d, a, x[k + 10], S34, 0xBEBFBC70);
                a = _HH(a, b, c, d, x[k + 13], S31, 0x289B7EC6);
                d = _HH(d, a, b, c, x[k + 0], S32, 0xEAA127FA);
                c = _HH(c, d, a, b, x[k + 3], S33, 0xD4EF3085);
                b = _HH(b, c, d, a, x[k + 6], S34, 0x4881D05);
                a = _HH(a, b, c, d, x[k + 9], S31, 0xD9D4D039);
                d = _HH(d, a, b, c, x[k + 12], S32, 0xE6DB99E5);
                c = _HH(c, d, a, b, x[k + 15], S33, 0x1FA27CF8);
                b = _HH(b, c, d, a, x[k + 2], S34, 0xC4AC5665);
                a = _II(a, b, c, d, x[k + 0], S41, 0xF4292244);
                d = _II(d, a, b, c, x[k + 7], S42, 0x432AFF97);
                c = _II(c, d, a, b, x[k + 14], S43, 0xAB9423A7);
                b = _II(b, c, d, a, x[k + 5], S44, 0xFC93A039);
                a = _II(a, b, c, d, x[k + 12], S41, 0x655B59C3);
                d = _II(d, a, b, c, x[k + 3], S42, 0x8F0CCC92);
                c = _II(c, d, a, b, x[k + 10], S43, 0xFFEFF47D);
                b = _II(b, c, d, a, x[k + 1], S44, 0x85845DD1);
                a = _II(a, b, c, d, x[k + 8], S41, 0x6FA87E4F);
                d = _II(d, a, b, c, x[k + 15], S42, 0xFE2CE6E0);
                c = _II(c, d, a, b, x[k + 6], S43, 0xA3014314);
                b = _II(b, c, d, a, x[k + 13], S44, 0x4E0811A1);
                a = _II(a, b, c, d, x[k + 4], S41, 0xF7537E82);
                d = _II(d, a, b, c, x[k + 11], S42, 0xBD3AF235);
                c = _II(c, d, a, b, x[k + 2], S43, 0x2AD7D2BB);
                b = _II(b, c, d, a, x[k + 9], S44, 0xEB86D391);
                a = addUnsigned(a, AA);
                b = addUnsigned(b, BB);
                c = addUnsigned(c, CC);
                d = addUnsigned(d, DD);
            }
            var temp = wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d);

            return temp.toLowerCase();
        },

        /**
         * Hashes given string using the CRC32 algorithm
         *
         * ATTENTION! Result depends on the encoding of the original string (must be utf8)!
         * Of violation of the limits of integer types, the hash can sometimes be negative
         * (depends on how you cast to a string, and 32/64 bit OC, especially on the back end)!
         * To get the unsigned value of the second parameter signed = false
         * @example <pre><code>
         *     resourceSmartLoad.fn.hashCrc32('azamat', false)       // In this case, reset the sign bit is irrelevant,
         *     // >>> 689152740                                      // since the number does not violate the boundaries of the iconic integer variables
         *     resourceSmartLoad.fn.hashCrc32('azamat', true)
         *     // >>> 689152740
         *     resourceSmartLoad.fn.hashCrc32('test')
         *     // >>> 3632233996
         *     resourceSmartLoad.fn.hashCrc32('test', true)
         *     // >>> -662733300
         * </code></pre>
         *
         * @param {String}  str             String
         * @param {Boolean} [signed=false]  If =true, returns signed number, otherwise - unsigned
         * @returns {Number}
         * @see http://phpjs.org/functions/crc32/
         * @see http://www.php.net/manual/ru/function.crc32.php
         */
        hashCrc32: function (str, signed) {
            signed = (signed == undefined) ? false : signed;
            // str = this.utf8_encode(str); // UTF8 disabled
            var table =
                '00000000 77073096 EE0E612C 990951BA 076DC419 706AF48F E963A535 9E6495A3 0EDB8832 79DCB8A4 E0D5E91E 97D2D988 09B64C2B 7EB17CBD E7B82D07 90BF1D91 1DB71064 6AB020F2 F3B97148 84BE41DE 1ADAD47D 6DDDE4EB F4D4B551 83D385C7 136C9856 646BA8C0 FD62F97A 8A65C9EC 14015C4F 63066CD9 FA0F3D63 8D080DF5 3B6E20C8 4C69105E D56041E4 A2677172 3C03E4D1 4B04D447 D20D85FD A50AB56B 35B5A8FA 42B2986C DBBBC9D6 ACBCF940 32D86CE3 45DF5C75 DCD60DCF ABD13D59 26D930AC 51DE003A C8D75180 BFD06116 21B4F4B5 56B3C423 CFBA9599 B8BDA50F 2802B89E 5F058808 C60CD9B2 B10BE924 2F6F7C87 58684C11 C1611DAB B6662D3D 76DC4190 01DB7106 98D220BC EFD5102A 71B18589 06B6B51F 9FBFE4A5 E8B8D433 7807C9A2 0F00F934 9609A88E E10E9818 7F6A0DBB 086D3D2D 91646C97 E6635C01 6B6B51F4 1C6C6162 856530D8 F262004E 6C0695ED 1B01A57B 8208F4C1 F50FC457 65B0D9C6 12B7E950 8BBEB8EA FCB9887C 62DD1DDF 15DA2D49 8CD37CF3 FBD44C65 4DB26158 3AB551CE A3BC0074 D4BB30E2 4ADFA541 3DD895D7 A4D1C46D D3D6F4FB 4369E96A 346ED9FC AD678846 DA60B8D0 44042D73 33031DE5 AA0A4C5F DD0D7CC9 5005713C 270241AA BE0B1010 C90C2086 5768B525 206F85B3 B966D409 CE61E49F 5EDEF90E 29D9C998 B0D09822 C7D7A8B4 59B33D17 2EB40D81 B7BD5C3B C0BA6CAD EDB88320 9ABFB3B6 03B6E20C 74B1D29A EAD54739 9DD277AF 04DB2615 73DC1683 E3630B12 94643B84 0D6D6A3E 7A6A5AA8 E40ECF0B 9309FF9D 0A00AE27 7D079EB1 F00F9344 8708A3D2 1E01F268 6906C2FE F762575D 806567CB 196C3671 6E6B06E7 FED41B76 89D32BE0 10DA7A5A 67DD4ACC F9B9DF6F 8EBEEFF9 17B7BE43 60B08ED5 D6D6A3E8 A1D1937E 38D8C2C4 4FDFF252 D1BB67F1 A6BC5767 3FB506DD 48B2364B D80D2BDA AF0A1B4C 36034AF6 41047A60 DF60EFC3 A867DF55 316E8EEF 4669BE79 CB61B38C BC66831A 256FD2A0 5268E236 CC0C7795 BB0B4703 220216B9 5505262F C5BA3BBE B2BD0B28 2BB45A92 5CB36A04 C2D7FFA7 B5D0CF31 2CD99E8B 5BDEAE1D 9B64C2B0 EC63F226 756AA39C 026D930A 9C0906A9 EB0E363F 72076785 05005713 95BF4A82 E2B87A14 7BB12BAE 0CB61B38 92D28E9B E5D5BE0D 7CDCEFB7 0BDBDF21 86D3D2D4 F1D4E242 68DDB3F8 1FDA836E 81BE16CD F6B9265B 6FB077E1 18B74777 88085AE6 FF0F6A70 66063BCA 11010B5C 8F659EFF F862AE69 616BFFD3 166CCF45 A00AE278 D70DD2EE 4E048354 3903B3C2 A7672661 D06016F7 4969474D 3E6E77DB AED16A4A D9D65ADC 40DF0B66 37D83BF0 A9BCAE53 DEBB9EC5 47B2CF7F 30B5FFE9 BDBDF21C CABAC28A 53B39330 24B4A3A6 BAD03605 CDD70693 54DE5729 23D967BF B3667A2E C4614AB8 5D681B02 2A6F2B94 B40BBE37 C30C8EA1 5A05DF1B 2D02EF8D';

            var crc = 0;
            var x = 0;
            var y = 0;

            crc = crc ^ (-1);
            for (var i = 0, iTop = str.length; i < iTop; i++) {
                y = (crc ^ str.charCodeAt(i)) & 0xFF;
                x = '0x' + table.substr(y * 9, 8);
                crc = (crc >>> 8) ^ x;
            }

            crc = crc ^ (-1)
            return signed
                ? crc
                : crc >>> 0; // Сбрасываем знаковый бит
        },

        /**
         * Hashes string CRC32 algorithm (HEX string format)
         * Adds zeros to the left, to get 8 digits 16 hex hashes (similar to php's function: hash('crc32b', str))
         *
         * @param {String}  str             String
         * @param {Boolean} [signed=false]  If =true, returns signed number, otherwise - unsigned
         * @returns {String}
         */
        hashCrc32Hex: function (str, signed) {
            var fn = this;
            return fn.lpad(fn.dechex(fn.hashCrc32(str, signed)), 8, '0');
        },

        /**
         * Returns a string representation of HEX-numbers
         *
         * @example <pre><code>
         *     Tvil.fn.dechex(10)
         *     // >>> 'a'
         *     Tvil.fn.dechex(47)
         *     // >>> '2f'
         *     Tvil.fn.dechex(-1415723993)
         *     // >>> 'ab9dc427'
         * </code></pre>
         *
         * @param {Number} number   Source number
         * @returns {String}        Representation of HEX-numbers
         */
        dechex   : function (number) {
            if (number < 0) {
                number = 0xFFFFFFFF + number + 1;
            }
            return parseInt(number, 10).toString(16);
        },

        // simplified from: https://github.com/epeli/underscore.string/blob/master/lpad.js
        //                  https://github.com/epeli/underscore.string/blob/master/pad.js
        lpad     : function (str, length, padStr) {
            var fn = this;

            length = ~~length;
            var padlen = 0;
            if (!padStr)
                padStr = ' ';
            else if (padStr.length > 1)
                padStr = padStr.charAt(0);

            padlen = length - str.length;
            return fn.strRepeat(padStr, padlen) + str;
        },

        // simplified from: https://github.com/epeli/underscore.string/blob/master/helper/strRepeat.js
        strRepeat: function (str, qty) {
            if (qty < 1) return '';
            var result = '';
            while (qty > 0) {
                if (qty & 1) result += str;
                qty >>= 1, str += str;
            }
            return result;
        }
    };


    // Public JS path:
    window.yiiResourceSmartLoad = resourceSmartLoad;
})(jQuery);