<?php
/**
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2011-12-28 17:26
 */
class UCache {
    // Cache instance
    private static $instance;

    /**
     * Returns Cache instance.
     *
     * @static
     * @return Cache
     */
    public static function &instance() {
        if (!self::$instance) {
            self::$instance = new UCache();
        }
        return self::$instance;
    }

    private $object;

    public function __construct() {
        if (IS_SAE) {
            // sae memcache
            if (function_exists('memcache_init')) {
                $this->object = new MCache();
                if ($this->object->init() === false) {
                    // KVDB
                    $this->object = new KVCache();
                    if ($this->object->init() === false) {
                        $this->object = new NOOPClass();
                    }
                }
            } else {
                $this->object = new NOOPClass();
            }
        } // file cache
        else {
            $this->object = new FCache();
        }
    }

    /**
     * 取得一条缓存数据
     *
     * @param string $key
     * @return mixed|string|void
     */
    public function get($key) {
        return $this->object->get($key);
    }

    /**
     * 设置缓存
     *
     * @param string $key
     * @param mixed $data
     * @param int $expire
     * @return bool|void
     */
    public function set($key, $data, $expire = 0) {
        return $this->object->set($key, $data, $expire);
    }

    /**
     * 删除一个key
     *
     * @param string $key
     * @return bool|void
     */
    public function delete($key) {
        return $this->object->delete($key);
    }

    /**
     * flush
     *
     * @return bool|void
     */
    public function flush() {
        return $this->object->flush();
    }

    /**
     * 判断结果是空值
     *
     * @static
     * @param mixed $data
     * @return bool
     */
    public function is_null($data) {
        if ($data === null || $data === false)
            return true;
        if (is_object($data)) {
            return get_class($data) == 'NOOPClass';
        }
        return false;
    }

    /**
     * 判断结果不是空值
     *
     * @param mixed $data
     * @return bool
     */
    public function not_null($data) {
        return $this->is_null($data) === false;
    }

    public function __call($name, $arguments) {
        return call_user_func_array(array(&$this->object, $name), $arguments);
    }
}
