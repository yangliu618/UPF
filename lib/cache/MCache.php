<?php
/**
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2011-12-28 17:29
 */
class MCache {
    private $object;
    public function __construct() {
        if(class_exists('Memcache')) {
            $this->object = new Memcache();
        } else {
            $this->object = new NOOPClass();
        }
    }

    public function __call($name, $arguments) {
        return call_user_func_array(array(&$this->object, $name), $arguments);
    }

    public function init() {
        return @$this->object->init();
    }

    public function get($key) {
        return $this->object->get($key);
    }

    public function set($key, $data, $expire=0) {
        if ($data === null) $data = new NOOPClass();
        return $this->object->set($key, $data, 0, $expire);
    }

    public function delete($key) {
        return $this->object->delete($key, 0);
    }

    public function flush() {
        return $this->object->flush();
    }
}
