<?php

/**
 * Helper class for RSmartLoadClientScript.
 * Contains common helper functions for data access/array manipulation/request variables reading.
 *
 * @author  G.Azamat <m@fx4web.com>
 * @link    http://fx4.ru/
 * @link    https://github.com/IStranger/yii-resource-smart-load
 * @version 0.11 (2015-02-07)
 * @since   1.1.14
 */
class RSmartLoadHelper
{
    /**
     * Filters values of given array by $callback.
     * If $callback function return true, current element included in result array
     *
     * <code>
     * // Select only elements with height>$data
     * $items = A::filter($a, function($key, $val, $data){{
     *      return $val['height'] > $data;
     * }, $data);
     * </code>
     *
     * @param array    $array
     * @param callable $callback
     * @param null     $data
     * @return array
     * @param boolean  $bind
     * @see  firstByFn(), lastByFn()
     * @uses execute()
     */
    public static function filterByFn(array $array, callable $callback, $data = null, $bind = true)
    {
        $handler = function (&$array, $key, $item, $result) {
            if ($result) {
                $array[$key] = $item;
            }
        };
        return self::_execute($array, $callback, $handler, $data, $bind);
    }

    /**
     * Returns a new array built using a callback.
     * <code>
     * // array
     * $array=A::createByFn($array,
     *      function($key,$item) {{return [$key.' '.count($item),$item];});
     * // $array=['key1 cnt1'=>$item1,'key1 cnt2'=>$item2,...];
     * // if array of objects
     * $objects=A::createByFn($objects,
     *      function() {{return [$this->name.'-'.$this->id,$this];});
     * </code>
     *
     * @param  array    $array
     * @param  callable $callback
     * @param  array    $data
     * @return array
     * @uses execute()
     */
    public static function createByFn($array, callable $callback, $data = null)
    {
        $handler = function (&$array, $key, $item, $result) {
            list($newKey, $newValue) = $result;
            $array[$newKey] = $newValue;
            return $result;
        };
        return self::_execute($array, $callback, $handler, $data, true);
    }


    /**
     * Extracts value of "client" variable from HTTP headers of current request.
     * If HTTP header not found, checks cookie (with the same name). <br/>
     * Works only for AJAX requests.
     *
     * @param string $name Name of "client" variable
     * @return string      Value of given variable. If not found, returns = null
     * @see Request::_getHttpHeader
     */
    public static function getClientVar($name)
    {
        if (!Yii::app()->request->isAjaxRequest) {
            return null;
        }
        $name = 'clientvar' . $name;
        $fromHeader = static::_getHttpHeader($name);
        $fromCookie = static::_getCookieValue($name); // value($this->cookies, $name.'.value');

        if ($fromHeader && $fromCookie && ($fromHeader !== $fromCookie)) { // По разным каналам должны прийти одинаковые данные (cookie - запасной)
            static::_throwException('Error in method Request::getClientVar >> from client obtained different data ' .
                'on the different channels (http-headers and cookie).');
        }
        return $fromHeader ? $fromHeader : $fromCookie;
    }

    /**
     * Returns value of HTTP header of <b><u>current</u></b> request (which has been sent from client to server). <br/>
     * By default, server stores these headers in {@link $_SERVER}.
     *
     * @param string $name            Name of header (case insensitive)
     * @param string $webserverPrefix Prefix of header names in array $_SERVER (it depends on the web server
     *                                configuration)
     * @return string                 Value of given HTTP header. If not found = null
     */
    private static function _getHttpHeader($name, $webserverPrefix = 'HTTP_')
    {
        $name = str_replace(array('-', ' '), '_', $name);
        return CHtml::value($_SERVER, mb_strtoupper($webserverPrefix . $name));
    }

    /**
     * Returns cookie value (by default attribute "value").<br/>
     * To access the values of other attributes, use parameter $attribute (possible value = name, value, domain, path,
     * expire, secure)
     *
     * @param string $cookieName Cookie name
     * @param mixed  $default    Default value, which returned if given cookie not found
     * @param string $attribute  Property of cookie object. {Available} properties see in {@link CHttpCookie}
     * @return string|null       Value of cookie (given property)
     * @see CHttpCookie
     * @see CCookieCollection
     */
    private static function _getCookieValue($cookieName, $default = null, $attribute = 'value')
    {
        return CHtml::value(Yii::app()->request->cookies, $cookieName . '.' . $attribute, $default);
    }

    /**
     * Returns result execute a $callback over each item of array.
     * Result prepare with execute $handler.
     *
     * @param array    $array
     * @param callable $callback
     * @param callable $handler
     * @param mixed    $data
     * @param boolean  $bind
     * @return array
     */
    private static function _execute($array, callable $callback, callable $handler, $data = null, $bind = false)
    {
        $resultValue = array();
        foreach ($array as $key => $item) {
            if (is_object($item)) {
                $item = clone $item;
                if ($callback instanceof Closure and $bind) {
                    $callback = Closure::bind($callback, $item);
                }
            }
            $result = call_user_func_array($callback, array($key, &$item, $data));
            if (call_user_func_array($handler, array(&$resultValue, $key, $item, $result)) === false) {
                break;
            }
        }
        return $resultValue;
    }

    /**
     * Throws exception with given message
     *
     * @throws Exception
     */
    private static function _throwException($msg = 'resource smart load exception')
    {
        YiiBase::log($msg, CLogger::LEVEL_ERROR, get_called_class());
        throw new Exception($msg); // you can change class of exception
    }
}