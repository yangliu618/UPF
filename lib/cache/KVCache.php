<?php
/**
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2011-12-28 17:29
 */
class KVCache {
    private $object;
    public function __construct() {
        if(class_exists('SaeKV')) {
            $this->object = new SaeKV();
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
        return $this->object->set($key, $data);
    }

    public function delete($key) {
        return $this->object->delete($key);
    }

    public function flush() {
        return ;
    }
}
