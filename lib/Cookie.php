<?php
// 定义域名
if (!defined('COOKIE_DOMAIN')) {
    $host = $_SERVER['HTTP_HOST'];
    define('COOKIE_DOMAIN', strncasecmp($host, 'www.', 4) === 0 ? substr($host, 4) : $host);
    unset($host);
}
/**
 * Cookie 管理类
 *
 * @author  Lukin <my@lukin.cn>
 * @version $Id$
 */
class Cookie {
    /**
     * 判断cookie是否存在
     *
     * @param string $name
     * @return bool
     */
    public static function is_set($name) {
        return isset($_COOKIE[$name]);
    }

    /**
     * 获取某个cookie值
     *
     * @param string $name
     * @return mixed
     */
    public static function get($name) {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
    }

    /**
     * 设置某个cookie值
     *
     * @param string $name
     * @param string $value
     * @param int    $expire
     * @param string $path
     * @param string $domain
     */
    public static function set($name, $value, $expire = 0, $path = '/', $domain = '') {
        if (empty($domain)) $domain = COOKIE_DOMAIN;
        if ($expire) $expire = time() + $expire;
        setcookie($name, $value, $expire, $path, $domain);
    }

    /**
     * 删除某个cookie值
     *
     * @param string $name
     * @param string $path
     * @param string $domain
     */
    public static function delete($name, $path = '/', $domain = '') {
        if (empty($domain)) $domain = COOKIE_DOMAIN;
        self::set($name, '', time() - 3600, $path, $domain);
    }
}
