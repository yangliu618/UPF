<?php
/**
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2012-08-02 14:44
 */

abstract class UPF_Page_Handler {
    // user info
    protected $USER;
    // 页面标题
    protected $title  = '';
    // 装饰器
    protected $wrap   = true;

    public function get_scripts() {
        return array();
    }
    public function get_styles() {
        return array();
    }
    public function get_page() {
        return UPF_PATH . '/page/default.phtml';
    }
    /**
     * url
     *
     * @param string $path
     * @param array $args
     * @return string
     */
    public function url($path, $args = null) {
        if ($args) {
            $query = http_build_query($args);
            if (strpos($path, '?') !== false) {
                $path .= '&' . $query;
            } else {
                $path .= '?' . $query;
            }
        }
        // rewrite
        if (get_config('app_rewrite')) {
            $path = APP_ROOT . $path;
        } else {
            $path = APP_ROOT . '?' . UPF_QFIELD . '=' . APP_ROOT . $path;
        }
        return $path;
    }

    /**
     * 魔术方法
     *
     * @param string $name
     * @param array $args
     * @return bool|mixed
     */
    public function __call($name, $args=null) {
        if (method_exists($this, $name)) {
            return call_user_func_array(array(&$this, $name), $args);
        } else {
            return false;
        }
    }

    /**
     * 执行 get请求
     */
    public function run_get() {
        ob_start();
        $args = func_get_args();
        $method = 'get';
        // XHR (must come first), iPad, mobile catch all
        if (is_xhr_request()) {
            // no cache
            header('Expires:' . date('D,d M Y H:i:s', time() - 86400 * 365) . ' GMT');
            header('Last-Modified:' . date('D,d M Y H:i:s') . ' GMT');
            header('Cache-Control:no-cache,must-revalidate');
            header('Pragma:no-cache');
        }
        // ipad
        if (is_ipad_request()) {
            $method.= '_ipad';
        }
        // mobile
        elseif (is_mobile_request()) {
            $method.= '_mobi';
        }
        // 执行代码
        if (!method_exists($this, $method)) {
            $method = 'get';
        }
        call_user_func_array(array(&$this, $method), $args);

        // 设置标题
        if ($this->title) {
            header('X-Page-Title: '. utf8tohtml($this->title, true));
            header('Content-Type: text/html; charset=utf-8');
        }

        $the_body = ob_block_end('body');
        if ($this->wrap) {
            $href_styles  = call_user_func(array(&$this, $method.'_styles'));
            $href_scripts = call_user_func(array(&$this, $method.'_scripts'));
            $text_styles  = ob_get_content('style');
            $text_scripts = ob_get_content('script');
            include call_user_func(array(&$this, $method.'_page'));
        } else {
            echo $the_body;
        }
    }

    /**
     * 执行 post请求
     */
    public function run_post() {
        ob_start();
        $args = func_get_args();
        $method = 'post';
        // XHR (must come first), iPad, mobile catch all
        if (is_xhr_request()) {
            $method.= '_xhr';
        }
        // ipad
        elseif (is_ipad_request()) {
            $method.= '_ipad';
        }
        // mobile
        elseif (is_mobile_request()) {
            $method.= '_mobi';
        }
        // 执行代码
        if (!method_exists($this, $method)) {
            $method = 'post';
        }
        call_user_func_array(array(&$this, $method), $args);
        $the_body = ob_block_end('body');
        quit($the_body);
    }
}