<?php
/**
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2012-10-28 21:45
 */

abstract class UPF_Lib_Base {
    /**
     * 返回实例
     *
     * @param string $class
     * @param string $method
     * @param array|null $args
     * @return object
     */
    protected static function &instance_of($class, $method, $args = null) {
        $object = new $class();
        if (method_exists($object, $method)) {
            if (!empty($args)) {
                call_user_func_array(array(&$object, $method), $args);
            } else {
                $object->$method();
            }
        }
        return $object;
    }

    private static $_instance = array();

    /**
     * 返回静态实例
     *
     * @param string $class
     * @param string $method
     * @param array|null $args
     * @return object
     */
    protected static function &factory_of($class, $method, $args = null) {
        $identify = $class . $method;
        if (!isset(self::$_instance[$identify])) {
            self::$_instance[$identify] = self::instance_of($class, $method, $args);
        }
        return self::$_instance[$identify];
    }
}
