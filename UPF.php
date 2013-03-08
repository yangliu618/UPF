<?php
/**
 * UTeng PHP Framework
 *
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2011-10-08 12:59
 */
// 检查环境是否适合做爱做的事
if (version_compare(PHP_VERSION, '5.0', '<')) {
    $_tip = 'PHP version below 5.0, Please upgrade!<br/> &lt;<a href="http://php.net/downloads.php" target="_blank">http://php.net/downloads.php</a>&gt;';
    die(PHP_SAPI == 'cli' ? str_replace(array('&lt;', '&gt;'), array('<', '>'), strip_tags($_tip))."\n" : $_tip);
}
// set error level
error_reporting() >= (E_ALL & ~E_NOTICE) or error_reporting(E_ALL & ~E_NOTICE);
// framework version
define('UPF_VER', '0.3');
// framework path
defined('UPF_PATH') or define('UPF_PATH', dirname(__FILE__));
// framework query field
defined('UPF_QFIELD') or define('UPF_QFIELD', 'q');
// check environment
define('IS_CGI', !strncasecmp(PHP_SAPI, 'cgi', 3) ? 1 : 0);
define('IS_WIN', DIRECTORY_SEPARATOR == '\\');
define('IS_CLI', PHP_SAPI == 'cli' ? 1 : 0);
define('IS_SAE', defined('SAE_TMP_PATH'));
// current file path
if (!defined('PHP_FILE')) {
    if (IS_CLI) {
        define('PHP_FILE', $argv[0]);
    } elseif (IS_CGI) {
        // CGI/FASTCGI mode
        $_temp = explode('.php', $_SERVER['PHP_SELF']);
        define('PHP_FILE', rtrim(str_replace($_SERVER['HTTP_HOST'], '', $_temp[0] . '.php'), '/'));
    } else {
        define('PHP_FILE', rtrim($_SERVER['SCRIPT_NAME'], '/'));
    }
}
// app version
defined('APP_VER') or define('APP_VER', '[APP_VER]');
// app path
defined('APP_PATH') or define('APP_PATH', UPF_PATH);
// app root
defined('APP_ROOT') or define('APP_ROOT', (($r = substr(str_replace('\\','/',dirname(PHP_FILE)), 0, ($i = strlen(substr(realpath('.'), strlen(APP_PATH)))) > 0 ? -$i : strlen(dirname(PHP_FILE)))) == '/' ? $r : $r . '/'));
// app static resource
defined('APP_RES') or define('APP_RES', APP_ROOT);
// http scheme
define('HTTP_SCHEME', (($scheme = isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : null) == 'off' || empty($scheme)) ? 'http' : 'https');
// tmp path
if (IS_WIN) {
    defined('TMP_PATH') or define('TMP_PATH', APP_PATH . '/tmp'); mkdirs(TMP_PATH);
} else {
    defined('TMP_PATH') or define('TMP_PATH', IS_SAE ? rtrim(SAE_TMP_PATH, '/') : '/tmp');
}
// set timezone
if (defined('TIME_ZONE')) {
    if (function_exists('date_default_timezone_set')) {
        date_default_timezone_set(TIME_ZONE);
    } else {
        putenv('TZ=' . TIME_ZONE);
    }
}
// Logger const variable
define('LOGGER_OFF',    0); // Nothing at all.
define('LOGGER_DEBUG',  1); // Most Verbose
define('LOGGER_INFO',   2); // ...
define('LOGGER_WARN',   3); // ...
define('LOGGER_ERROR',  4); // ...
define('LOGGER_FATAL',  5); // ...
define('LOGGER_LOG',    6); // Least Verbose
// register autoload
spl_autoload_register(array('App', '__autoload'));
// process lib Variable
$error_level = error_reporting(0);
if (get_magic_quotes_gpc()) {
    function stripslashes_deep($value) {
        return is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
    }
    $args = array(& $_GET, & $_POST, & $_COOKIE, & $_REQUEST);
    while (list($k, $v) = each($args)) {
        $args[$k] = stripslashes_deep($args[$k]);
    }
    unset($args, $k, $v);
}
error_reporting($error_level);




/**
 * 取得配置
 *
 * @param string|null $name
 * @param string $file
 * @return null|string|array
 */
function get_config($name=null, $file='common') {
    static $configures;
    // 读取多个参数，并合并
    if (strpos($name, ',') !== false) {
        $result = array();
        $names  = explode(',', $name);
        foreach($names as $key) {
            if ($config = get_config($key, $file)) {
                $result = array_merge_recursive_distinct($result, $config);
            }
        }
        return $result;
    }

    if (!isset($configures[$file])) {
        /**
         * default config
         *
         * @author Lukin <my@lukin.cn>
         * @datetime 2011-10-09 14:09
         */
        $default = array(
            // allow domain
            'crossdomain' => IS_CLI ? '*' : '*.' . $_SERVER['HTTP_HOST'],
            // 输出日志允许的IP
            'logger_allowIPs' => '127.0.0.1',
            // 默认使用rewrite
            'app_rewrite' => true,
            // app common autoload
            'app_autoload' => array(
                '^DB_(.+?)$' => UPF_PATH . '/lib/db/$1.php',
                '^(KVCache|FCache|MCache)$' => UPF_PATH . '/lib/cache/$1.php',
                '^Spyc$' => UPF_PATH . '/lib/spyc.php',
                '^Services_JSON$' => UPF_PATH . '/lib/JSON.php',
                '^(DBQuery|HTMLFixer|MailDecode|Pagebreak|Cookie|PHPMailer|SMTP|UCache|Logger|Validate|Upload|QQWry|Image|Httplib)$' => UPF_PATH . '/lib/$1.php',
            ),
            // app route rules
            'app_routes' => array(),
        );
        $config  = load_config($file);
        $config  = array_merge_recursive_distinct($default, $config);
        // 加载最外层的配置，优先级最高
        if ($static = load_config($file, '__super__')) {
            $config = array_merge_recursive_distinct($config, $static);
        }
        if (!$config) {
            return null;
        }
        $configures[$file] = $config;
    }
    if ($name == null) {
        return isset($configures[$file]) ? $configures[$file] : null;
    } else {
        return isset($configures[$file][$name]) ? $configures[$file][$name] : null;
    }
}

/**
 * 取得数据库链接
 *
 * @return DBQuery
 */
function &get_conn() {
    global $db;
    if (is_null($db) || $db instanceof NOOPClass) {
        $DSN = get_config('DB_DSN', 'database');
        if ($DSN) {
            $user = get_config('DB_USER', 'database');
            $pwd  = get_config('DB_PWD', 'database');
            $db   = DBQuery::factory($DSN, $user, $pwd);
        } else {
            $db = new NOOPClass();
        }
    }
    return $db;
}

/**
 * 块开始
 *
 * @return void
 */
function ob_block_start() {
    global $upf_tmp_content;
    $upf_tmp_content = ob_get_contents();
    ob_clean(); ob_start();
}
/**
 * get ob content for tag
 *
 * @param string $tag
 * @param string $join
 * @return array|null
 */
function ob_get_content($tag, $join = "\r\n") {
    global $upf_ob_contents;
    if (isset($upf_ob_contents[$tag])) {
        array_multisort($upf_ob_contents[$tag]['order'], SORT_DESC, $upf_ob_contents[$tag]['content']);
        return implode($join, $upf_ob_contents[$tag]['content']);
    }
    return null;
}
/**
 * set ob content for tag
 *
 * @param string $tag
 * @param int $order
 * @return array|null
 */
function ob_block_end($tag, $order = 0) {
    global $upf_ob_contents, $upf_tmp_content;
    $content = ob_get_contents(); ob_clean();
    if (!isset($upf_ob_contents[$tag])) $upf_ob_contents[$tag] = array();
    $upf_ob_contents[$tag]['content'][] = $content;
    $upf_ob_contents[$tag]['order'][] = $order;
    if ($upf_tmp_content) {
        echo $upf_tmp_content; $upf_tmp_content = '';
    }
    return ob_get_content($tag);
}
/**
 * 加载配置
 *
 * @param string $file
 * @param string $super
 * @return array
 */
function load_config($file, $super=null) {
    $config = array();
    // 先加载 UPF_PATH 目录
    if (is_ifile(UPF_PATH . '/conf/' . $file . '.php')) {
        include UPF_PATH . '/conf/' . $file . '.php';
    }
    // 加载 APP_PATH 目录
    if (is_ifile(APP_PATH . '/conf/' . $file . '.php')) {
        include APP_PATH . '/conf/' . $file . '.php';
    }
    // 加载钩子里的配置文件
    $path = apply_filters('load_config', $file, $super);
    if ($path != $file && is_ifile($path)) {
        include $path;
    }
    return $config;
}
/**
 * throw exception
 *
 * @throws UPF_Exception
 * @param string $error
 * @param int $errno
 * @return void
 */
function upf_error($error, $errno = 500) {
    if (error_reporting() == 0) return false;
    throw new UPF_Exception($error, $errno);
}
/**
 * catch exception
 *
 * @param Exception $e
 * @return void
 */
function upf_handler_error(&$e) {
    $code = $e->getCode();
    $data = $e->getMessage();
    if ($code > 0) {
        $trace = $e->getTrace(); $error = $trace[0];
        $log = sprintf("%s\r\n", $data);
        $log.= sprintf("[File]:\r\n\t%s (%d)\r\n", $error['file'], $error['line']);
        $log.= sprintf("[Trace]:\r\n%s\r\n", $e->getStackTrace());
        // handler error
        apply_filters('upf_handler_error', 'logs', $log);
    } else {
        $data = $e->data;
        // not null
        if ($data !== null) {
            if (!is_scalar($data)) {
                if (is_xhr_request()) {
                    if (is_accept_json())
                        header('Content-Type: application/json; charset=utf-8');
                    $data = json_encode($data);
                    $data = apply_filters('upf_handler_error', 'json', $data);
                } else {
                    $data = print_r($data, true);
                    $data = apply_filters('upf_handler_error', 'text', $data);
                }
            }
            echo $data;
        }
    }
}
/**
 * exit as quit
 *
 * @throws UPF_Exception
 * @param mixed $data
 * @return void
 */
function quit($data = null) {
    if (func_num_args() >= 2) {
        $args    = func_get_args();
        $status  = array_shift($args);
        $message = array_shift($args);
        if ($args) {
            $data = $args;
            $data['status']  = $status;
            $data['message'] = $message;
        } else {
            $data = array(
                'status'  => $status,
                'message' => $message,
            );
        }
    }
    throw new UPF_Exception($data);
}
/**
 * 添加过滤器
 *
 * @param string $tag
 * @param string $function
 * @param int $priority
 * @param int $accepted_args
 * @return bool
 */
function add_filter($tag, $function, $priority = 10, $accepted_args = 1) {
    global $upf_filter; static $filter_id_count = 0;
    if (is_string($function)) {
        $idx = $function;
    } else {
        if (is_object($function)) {
            // Closures are currently implemented as objects
            $function = array($function, '');
        } else {
            $function = (array)$function;
        }
        if (is_object($function[0])) {
            // Object Class Calling
            if (function_exists('spl_object_hash')) {
                $idx = spl_object_hash($function[0]) . $function[1];
            } else {
                $idx = get_class($function[0]) . $function[1];
                if (!isset($function[0]->upf_filter_id)) {
                    $idx .= isset($upf_filter[$tag][$priority]) ? count((array)$upf_filter[$tag][$priority])
                            : $filter_id_count;
                    $function[0]->upf_filter_id = $filter_id_count;
                    ++$filter_id_count;
                } else {
                    $idx .= $function[0]->upf_filter_id;
                }
            }
        } else if (is_string($function[0])) {
            // Static Calling
            $idx = $function[0] . $function[1];
        }
    }
    $upf_filter[$tag][$priority][$idx] = array('function' => $function, 'accepted_args' => $accepted_args);
    return true;
}
/**
 * Call the functions added to a filter hook.
 *
 * @param string $tag
 * @param mixed $value
 * @return mixed
 */
function apply_filters($tag, $value) {
    global $upf_filter;

    if (!isset($upf_filter[$tag])) {
        return $value;
    }

    ksort($upf_filter[$tag]);

    reset($upf_filter[$tag]);

    $args = func_get_args();

    do {
        foreach ((array)current($upf_filter[$tag]) as $self)
            if (!is_null($self['function'])) {
                $args[1] = $value;
                $value = call_user_func_array($self['function'], array_slice($args, 1, (int)$self['accepted_args']));
            }

    } while (next($upf_filter[$tag]) !== false);

    return $value;
}

/**
 * UTeng PHP Framework App Class
 */
final class App {
    // app begin memory usage
    private $memory_usage;
    // app begin time
    private $begin_time;
    // shut down function
    private $shutdowns;
    // load pairs
    private $load_pairs;
    // route pairs
    private $route_pairs;
    // app uri
    private $uri;
    // use rewrite
    private $rewrite;
    // qfield
    private $qfield;

    public function __construct() {
        // set app begin memory usage
        $this->memory_usage = memory_get_usage();
        // set app begin time
        $this->begin_time = microtime(true);
        // autoload rules
        $this->load_pairs = get_config('app_autoload');
        // routes rules
        $this->route_pairs = get_config('app_routes');
        // run mode
        if (IS_CLI) {
            ob_implicit_flush(1);
            // set uri
            $this->uri = PHP_FILE;
        } else {
            ob_start();
            // uri
            $request_uri = $_SERVER['REQUEST_URI'];
            if (($pos = strpos($request_uri, '?')) !== false) $request_uri = substr($request_uri, 0, $pos);
            if ($request_uri == APP_ROOT) {
                $this->rewrite = !is_ifile(dirname(UPF_PATH) . PHP_FILE);
            } else {
                $this->rewrite = ($request_uri != PHP_FILE) && (!is_ifile(dirname(UPF_PATH) . $request_uri));
            }
            $this->qfield  = isset($_GET[UPF_QFIELD]);
            if ($this->rewrite || $this->qfield) {
                $field = UPF_QFIELD;
                // Apache 404
                if (isset($_SERVER['REDIRECT_STATUS']) && $_SERVER['REDIRECT_STATUS'] == 404) {
                    header('HTTP/1.1 200 OK', true, 200);
                    $uri = $_SERVER['REQUEST_URI'];
                }
                // IIS 404
                elseif (isset($_SERVER['QUERY_STRING']) && strncmp($_SERVER['QUERY_STRING'], '404;', 4) === 0) {
                    header('HTTP/1.1 200 OK', true, 200);
                    $_SERVER['URL'] = $_SERVER['REQUEST_URI'] = substr($_SERVER['QUERY_STRING'], strlen('404;'.HTTP_SCHEME.'://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT']));
                    $_SERVER['QUERY_STRING'] = substr($_SERVER['QUERY_STRING'], strpos($_SERVER['QUERY_STRING'], '?') + 1);
                    $uri = $_SERVER['REQUEST_URI'];
                }
                // QUERY_STRING
                elseif ($field && isset($_GET[$field]) && empty($_SERVER['PATH_INFO'])) {
                    $uri = isset($_GET[$field]) ? $_GET[$field] : '';
                    unset($_GET[$field], $_REQUEST[$field]);
                    $uri = strncmp($uri, '/', 1) === 0 ? $uri : '/' . $uri;
                }
                // PATH_INFO
                elseif (strpos($_SERVER['REQUEST_URI'], PHP_FILE) === 0) {
                    $uri = substr($_SERVER['REQUEST_URI'], strlen(PHP_FILE));
                    $uri = strncmp($uri, '/', 1) === 0 ? $uri : '/' . $uri;
                }
                // other
                else {
                    $uri = $_SERVER['REQUEST_URI'];
                }
                // handle query
                if (($pos=strpos($uri, '?')) !== false) {
                    $query = substr($uri, $pos+1);
                    $uri   = substr($uri, 0, $pos);
                    parse_str($query, $_GET);
                    $_REQUEST = array_merge($_REQUEST, $_GET);
                }
                if (empty($uri)) $uri = '/';
                // set uri
                $this->uri = $uri;
            } else {
                // set uri
                $this->uri = $request_uri;
            }
        }
    }

    // App instance
    private static $instance;
    /**
     * Returns App instance.
     *
     * @static
     * @return App
     */
    public static function &instance() {
        if (!self::$instance) {
            self::$instance = new App();
        }
        return self::$instance;
    }
    /**
     * get uri
     *
     * @return string
     */
    public function get_uri() {
        return $this->uri;
    }

    /**
     * set autoload rules
     *
     * @param array|null $load_pairs
     * @return App
     */
    public function autoload($load_pairs = null) {
        if ($load_pairs && is_array($load_pairs)) {
            $this->load_pairs = array_merge_recursive_distinct($this->load_pairs, $load_pairs);
        }
        return $this;
    }
    /**
     * set rewrite rules
     *
     * @param array|null $route_pairs
     * @return App
     */
    public function rewrite($route_pairs = null) {
        if ($route_pairs && is_array($route_pairs)) {
            $this->route_pairs = array_merge_recursive_distinct($this->route_pairs, $route_pairs);
        }
        return $this;
    }
    /**
     * 执行函数
     *
     * @param string $func
     * @return App
     */
    public function apply($func) {
        $args = func_get_args();
        array_shift($args);
        call_user_func_array($func, $args);
        return $this;
    }
    // route matches
    private $route_matches = array();
    /**
     * UTeng PHP framework run
     *
     * @param array|null $route_pairs
     * @return App
     */
    public function run($route_pairs = null) {
        // 防止重复执行
        if (defined('__RunHandler__')) {
            return $this;
        } else {
            define('__RunHandler__', true);
        }
        // match Handler
        $pathinfo = pathinfo(PHP_FILE);
        if (!isset($pathinfo['filename'])) {
            $pathinfo['filename'] = substr($pathinfo['basename'], 0, strrpos($pathinfo['basename'], '.'));
            if (empty($pathinfo['filename'])) // there's no extension
                $pathinfo['filename'] = $pathinfo['basename'];
        }
        if (is_null($route_pairs) || is_array($route_pairs)) {
            $handler = preg_replace('/[^a-zA-Z0-9\_]/', '', $pathinfo['filename']) . 'Handler';
        } else {
            $handler = $route_pairs;
        }
        // 非CLI模式
        if (!IS_CLI && ($this->rewrite || $this->qfield)) {
            $handler = 'HTTP404';
            // rewrite uri
            $match_uri = $this->rewrite ? substr($this->uri, strlen(APP_ROOT) - 1) : $this->uri;

            // route matches
            if ($route_pairs && is_array($route_pairs)) {
                $this->route_pairs = array_merge($this->route_pairs, $route_pairs);
            }
            if ($this->route_pairs) {
                $matches = array();
                foreach ($this->route_pairs as $class => $mapping) {
                    if (is_array($mapping)) {
                        $is_assoc = is_assoc($mapping);
                        foreach ($mapping as $id=>$pattern) {
                            if (preg_match('@'.$pattern.'@', $match_uri, $matches)) {
                                if ($is_assoc) $matches[] = $id;
                                $this->route_matches = $matches;
                                $handler = $class; break 2;
                            }
                        }
                    } else {
                        if (preg_match('@'.$mapping.'@', $match_uri, $matches)) {
                            $this->route_matches = $matches;
                            $handler = $class; break;
                        }
                    }
                }
            }
            empty($this->route_matches) ? array_unshift($this->route_matches, $this->uri) : null;
        } else {
            array_unshift($this->route_matches, $this->uri);
        }
        // dispatch
        $this->dispatch($handler);
        return $this;
    }
    /**
     * dispatch
     *
     * @param string $handler
     * @return void
     */
    public function dispatch($handler) {
        $classfile = $this->class2file($handler);
        if ($classfile && !isset($this->class2file[$handler])) {
            include $classfile;
        } elseif ($this->rewrite && !class_exists($handler)) {
            $handler = 'HTTP404';
        }

        try {
            $is_404 = true;
            if ($handler && class_exists($handler)) {
                if ($handler == 'HTTP404') header('HTTP/1.1 404 Not Found', true, 404);
                // 初始化类
                $handle = new $handler();
                // 执行方式
                $method = apply_filters('apprun_method', IS_CLI ? 'get' : strtolower($_SERVER['REQUEST_METHOD']));
                // arguments
                $arguments = $this->route_matches ? $this->route_matches : array();
                // 类可以访问
                if (($run_get_define = method_exists($handle, 'run_' . $method)) || ($get_define = method_exists($handle, $method))) {
                    $is_404 = false;
                    // 优先执行 run_method
                    if ($run_get_define) {
                        $method = 'run_' . $method;
                    }
                    // 通用前置事件
                    if (method_exists($handler, '__before')) {
                        call_user_func_array(array(&$handle, '__before'), $arguments);
                    }
                    // 执行方法
                    call_user_func_array(array(&$handle, $method), $arguments);
                    // 通用后置事件
                    if (method_exists($handler, '__after')) {
                        call_user_func_array(array(&$handle, '__after'), $arguments);
                    }
                }
            }

            // 404 Page
            if ($is_404) {
                header('HTTP/1.1 404 Not Found', true, 404);
                if ($handler == 'HTTP404') {
                    if (IS_CLI) {
                        quit('Error: '.$handler.'->run() Not Found');
                    } else {
                        quit('<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL '.$this->get_uri().' was not found on this server.</p></body></html>');
                    }
                } else {
                    $this->dispatch('HTTP404');
                }
            }
        } catch (Exception $e) {
            upf_handler_error($e);
        }
    }

    // cache for class 2 file
    private $class2file = array();
    /**
     * class to file
     *
     * @param string $classname
     * @return string|null
     */
    public function class2file($classname) {
        // get cache
        if (isset($this->class2file[$classname])) {
            return $this->class2file[$classname] == 'php://null' ? null : $this->class2file[$classname];
        }
        // init cache
        $file = null;
        if (is_string($classname) && $this->load_pairs && preg_match('/^[a-zA-Z][\w]+?$/', $classname)) {
            foreach ($this->load_pairs as $pattern => $paths) {
                if (is_array($paths)) {
                    foreach ($paths as $path) {
                        if (($file=preg_replace('@'.$pattern.'@', $path, $classname)) != $classname && is_ifile($file)) {
                            break 2;
                        }
                    }
                } else {
                    if (($file=preg_replace('@'.$pattern.'@', $paths, $classname)) != $classname && is_ifile($file)) {
                        break;
                    }
                }
            }
            // handle prefix
            if (strncmp($file, '@', 1) === 0) {
                $file = APP_PATH . substr($file, 1);
            }
            if ($file == APP_PATH . PHP_FILE) {
                $file = null;
            } else {
                $file = is_ifile($file) ? $file : null;
            }
        }

        // set cache
        $this->class2file[$classname] = $file ? $file : 'php://null';
        return $file;
    }
    /**
     * 自动加载
     *
     * @param string $classname
     * @return void
     */
    public static function __autoload($classname) {
        $file = App::instance()->class2file($classname);
        if ($file) include $file;
    }
    /**
     * register shutdown function
     *
     * @param string $function
     * @param array $arguments
     * @return array|null
     */
    public function register_shutdown($function, $arguments = array()) {
        $this->shutdowns[] = array(
            'function'  => $function,
            'arguments' => $arguments,
        );
        return $this->shutdowns;
    }
    /**
     * app shutdown
     *
     * @return void
     */
    public function __destruct() {
        while ($this->shutdowns && is_array($this->shutdowns)) {
            $functions = array_reverse($this->shutdowns); $this->shutdowns = array();
            foreach ($functions as $function) {
                call_user_func_array($function['function'], $function['arguments']);
            }
        }
        // cli mode
        if (IS_CLI) {
            printf("\n\n---------- %s ----------\n", date('Y-m-d H:i:s', time()));
            printf("Powered-By: UPF/%s (UTeng.net)\n", UPF_VER);
            printf("Run-App: root=%s; version=%s; sql_exec=%s\n", APP_ROOT, APP_VER, DBQuery::$query_count);
            printf("Runtime: %s\n", microtime(true) - $this->begin_time);
            printf("Memory-Usage: %s\n\n", format_size(memory_get_usage() - $this->memory_usage) . ' / ' . ini_get('memory_limit'));
        }
        // http mode
        else {
            // must start ob_start();
            header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
            header('X-Powered-By: UPF/' . UPF_VER . ' (UTeng.net)');
            header('X-Run-App: root=' . APP_ROOT . '; version=' . APP_VER.'; sql_exec='.(DBQuery::$query_count));
            header('X-Runtime: ' . (microtime(true) - $this->begin_time));
            header('X-Memory-Usage: ' . format_size(memory_get_usage() - $this->memory_usage) . ' / ' . ini_get('memory_limit'));
            // flush content to browser
            ob_end_flush(); flush();
        }
    }
    /**
     * 允许的域名
     *
     * @param string $domain
     * @return bool
     */
    public function allowDomain($domain) {
        $crossdomain = get_config('crossdomain');
        $crossdomain = $crossdomain ? $crossdomain : '*.' . $_SERVER['HTTP_HOST'];
        $crossdomain = '/(' . str_replace(array('.','*'), array('\.','.*'), $crossdomain) . ')$/i';
        return preg_match($crossdomain, $domain);
    }
}

/**
 * UTeng PHP Framework Exception
 */
class UPF_Exception extends Exception {
    /**
     * Transfer data
     *
     * @var null|string
     */
    public $data = null;
    /**
     * construct
     *
     * @param string $message
     * @param int $code
     */
    public function __construct($message, $code = 0) {
        $this->data = $message;
        if ($message !== null && !is_scalar($message)) {
            $message = sprintf('[%s]', ucfirst(gettype($message)));
        }
        parent::__construct($message, $code);
    }

    /**
     * 自定义输出
     *
     * @return string
     */
    public function __toString() {
        return sprintf('%s: [%d]: %s', __CLASS__, $this->code, $this->message);
    }
    /**
     * 异常堆栈
     *
     * @return string
     */
    public function getStackTrace() {
        $string = $file = null;
        $traces = $this->getTrace(); unset($traces[0]);
        array_splice($traces, count($traces)-4, -1);
        foreach ($traces as $i => $trace) {
            $file = isset($trace['file']) ? hide_path($trace['file']) : $file;
            $line = isset($trace['line']) ? $trace['line'] : null;
            $class = isset($trace['class']) ? $trace['class'] : null;
            $type = isset($trace['type']) ? $trace['type'] : null;
            $args = isset($trace['args']) ? $trace['args'] : null;
            $function = isset($trace['function']) ? $trace['function'] : null;
            $string .= "\t#" . $i . ' [' . date('y-m-d H:i:s') . '] ' . $file . ($line ? '(' . $line . ') ' : ' ');
            $string .= $class . $type . $function . '(';
            if (is_array($args)) {
                $arrs = array();
                foreach ($args as $v) {
                    if (is_object($v)) {
                        $arrs[] = implode(' ', get_object_vars($v));
                    } else {
                        $error_level = error_reporting(0);
                        $vars = print_r($v, true);
                        error_reporting($error_level);
                        while (strpos($vars, chr(32) . chr(32)) !== false) {
                            $vars = str_replace(chr(32) . chr(32), chr(32), $vars);
                        }
                        $arrs[] = $vars;
                    }
                }
                $string .= str_replace("\n", '', implode(', ', $arrs));
            }
            $string .= ")\r\n";
        }
        return $string;
    }
}
/**
 * NOOP Class
 */
class NOOPClass {
    public function __call($name, $args) {
        return ;
    }
}


/**
 *
 * 公共函数库
 *
 *******************************************************/


/**
 * printf as e
 *
 * @return bool|mixed
 */
function e() {
    $args = func_get_args();
    if (count($args) == 1) {
        echo $args[0];
    } else {
        return call_user_func_array('printf', $args);
    }
    return true;
}
/**
 * 翻译工具
 *
 * @param string $text
 * @param string $domain
 * @return string
 */
function __($text, $domain = 'default') {
    return apply_filters('__', $text, $domain);
}
/**
 * 输出
 *
 * @param string $text
 * @param string $domain
 * @return void
 */
function _e($text, $domain = 'default') {
    echo __($text, $domain);
}
/**
 * 转义SQL查询字符
 *
 * @param string|array $value
 * @return string
 */
function esc_sql($value) {
    if (function_exists('get_conn')) {
        return get_conn()->escape($value);
    } else {
        return $value;
    }
}
/**
 * 转换特殊字符为HTML实体
 *
 * @param   string $str
 * @return  string
 */
function esc_html($str){
    if(empty($str)) {
        return $str;
    } elseif (is_array($str)) {
        $str = array_map('esc_html', $str);
    } elseif (is_object($str)) {
        $vars = get_object_vars($str);
        foreach ($vars as $key=>$data) {
            $str->{$key} = esc_html($data);
        }
    } else {
        $str = htmlspecialchars($str);
    }
    return $str;
}
/**
 * Escapes strings to be included in javascript
 *
 * @param string $str
 * @return mixed
 */
function esc_js($str) {
    return str_replace(
        array("\r", "\n"),
        array('', ''),
        addcslashes(esc_html($str), "'")
    );
}
/**
 * is_file(Case sensitive)
 *
 * @param string $filename
 * @return bool
 */
function is_ifile($filename) {
    if (@is_file($filename)) {
        if (IS_WIN) {
            if (basename(realpath($filename)) != basename($filename))
                return false;
        }
        return true;
    }
    return false;
}
/**
 * ajax request
 *
 * @return bool
 */
function is_xhr_request() {
    return ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : null)
            || (isset($_POST['X-Requested-With']) ? $_POST['X-Requested-With'] : null)) == 'XMLHttpRequest';
}
/**
 * ipad request
 *
 * @return bool
 */
function is_ipad_request() {
    return strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') !== false;
}
/**
 * mobile request
 *
 * @return bool
 */
function is_mobile_request() {
    return strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false
           || strpos($_SERVER['HTTP_USER_AGENT'], 'iPod') !== false
           || strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false
           || strpos($_SERVER['HTTP_USER_AGENT'], 'webOS') !== false;
}
/**
 * accept json
 *
 * @return bool
 */
function is_accept_json() {
    return strpos(strtolower((isset($_POST['X-Http-Accept']) ? $_POST['X-Http-Accept'].',' : '') . $_SERVER['HTTP_ACCEPT']), 'application/json') !== false;
}
/**
 * 检查数组类型
 *
 * @param array $array
 * @return bool
 */
function is_assoc($array) {
    return (is_array($array) && (0 !== count(array_diff_key($array, array_keys(array_keys($array)))) || count($array)==0));
}
/**
 * 检查值是否已经序列化
 *
 * @param mixed $data Value to check to see if was serialized.
 * @return bool
 */
function is_serialized($data) {
    // if it isn't a string, it isn't serialized
    if (!is_string($data))
        return false;
    $data = trim($data);
    if ('N;' == $data)
        return true;
    if (!preg_match('/^([adObis]):/', $data, $badions))
        return false;
    switch ($badions[1]) {
        case 'a' :
        case 'O' :
        case 's' :
            if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data))
                return true;
            break;
        case 'b' :
        case 'i' :
        case 'd' :
            if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data))
                return true;
            break;
    }
    return false;
}
/**
 * 根据概率判定结果
 *
 * @param float $probability
 * @return bool
 */
function is_happened($probability){
    return (mt_rand(1, 100000) / 100000) <= $probability;
}

/**
 * 判断是否汉字
 *
 * @param string $str
 * @return int
 */
function is_hanzi($str){
    return preg_match('%^(?:
          [\xC2-\xDF][\x80-\xBF]            # non-overlong 2-byte
        | \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
        | \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
        | \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}         # planes 4-15
        | \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
        )*$%xs',$str);
}

/**
 * 判断是否搜索蜘蛛
 *
 * @static
 * @return bool
 */
function is_spider() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    if (stripos($user_agent, 'Googlebot') !== false
        || stripos($user_agent, 'Sosospider') !== false
        || stripos($user_agent, 'Baiduspider') !== false
        || stripos($user_agent, 'Baidu-Transcoder') !== false
        || stripos($user_agent, 'Yahoo! Slurp') !== false
        || stripos($user_agent, 'iaskspider') !== false
        || stripos($user_agent, 'Sogou') !== false
        || stripos($user_agent, 'YodaoBot') !== false
        || stripos($user_agent, 'msnbot') !== false
        || stripos($user_agent, 'Sosoimagespider') !== false
    ) {
        return true;
    }
    return false;
}
/**
 * 全角转半角
 *
 * @param string $str
 * @return string
 */
function semiangle($str) {
    $arr = array(
        '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
        '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
        'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
        'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
        'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
        'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
        'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
        'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
        'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
        'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
        'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
        'ｙ' => 'y', 'ｚ' => 'z',

        'ā' => 'a', 'á' => 'a', 'ǎ' => 'a', 'à' => 'a','ō' => 'o',
        'ó' => 'o', 'ǒ' => 'o', 'ò' => 'o', 'ê' => 'e', 'ē' => 'e', 'é' => 'e',
        'ě' => 'e', 'è' => 'e', 'ī' => 'i', 'í' => 'i', 'ǐ' => 'i', 'ì' => 'i',
        'ū' => 'u', 'ú' => 'u', 'ǔ' => 'u', 'ù' => 'u', 'ǖ' => 'v', 'ǘ' => 'v',
        'ǚ' => 'v', 'ǜ' => 'v', 'ü' => 'v', 'ɡ' => 'g',


        '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[', '】' => ']',
        '〖' => '[', '〗' => ']', '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
        '｛' => '{', '｝' => '}', '《' => '<', '》' => '>',

        '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
        '：' => ':', '。' => '.', '、' => ',', '，' => ',',
        '；' => ';', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
        '｜' => '|', '〃' => '"', '　' => ' ',

    );
    return strtr($str, $arr);
}
/**
 * 全概率计算
 *
 * @param array $input array('a'=>0.5,'b'=>0.2,'c'=>0.4)
 * @param int $pow 小数点位数
 * @return array key
 */
function random($input, $pow = 2) {
    $much = pow(10, $pow);
    $max  = array_sum($input) * $much;
    $rand = mt_rand(1, $max);
    $base = 0;
    foreach ($input as $k => $v) {
        $min = $base * $much + 1;
        $max = ($base + $v) * $much;
        if ($min <= $rand && $rand <= $max) {
            return $k;
        } else {
            $base += $v;
        }
    }
    return false;
}
/**
 * 随机字符串
 *
 * @param int $length
 * @param string $charlist
 * @return string
 */
function str_rand($length = 6, $charlist = '0123456789abcdefghijklmnopqrstopwxyz') {
    $charcount = strlen($charlist); $str = null;
    for ($i = 0; $i < $length; $i++) {
        $str .= $charlist[mt_rand(0, $charcount - 1)];
    }
    return $str;
}
/**
 * 在数组或字符串中查找
 *
 * @param mixed  $needle   需要搜索的字符串
 * @param string|array $haystack 被搜索的数据，字符串用英文“逗号”分割或数组
 * @return bool
 */
function instr($needle,$haystack){
    if (empty($haystack)) { return false; }
    if (!is_array($haystack)) $haystack = explode(',',$haystack);
    return in_array($needle,$haystack);
}
/**
 * converts a UTF8-string into HTML entities
 *
 * @param string $content   the UTF8-string to convert
 * @param bool $encodeTags  booloean. TRUE will convert "<" to "&lt;"
 * @return string           returns the converted HTML-string
 */
function utf8tohtml($content, $encodeTags = true) {
    $result = '';
    for ($i = 0; $i < strlen($content); $i++) {
        $char = $content[$i];
        $ascii = ord($char);
        if ($ascii < 128) {
            // one-byte character
            $result .= ($encodeTags) ? htmlentities($char) : $char;
        } else if ($ascii < 192) {
            // non-utf8 character or not a start byte
        } else if ($ascii < 224) {
            // two-byte character
            $result .= htmlentities(substr($content, $i, 2), ENT_QUOTES, 'UTF-8');
            $i++;
        } else if ($ascii < 240) {
            // three-byte character
            $ascii1 = ord($content[$i + 1]);
            $ascii2 = ord($content[$i + 2]);
            $unicode = (15 & $ascii) * 4096 +
                    (63 & $ascii1) * 64 +
                    (63 & $ascii2);
            $result .= "&#$unicode;";
            $i += 2;
        } else if ($ascii < 248) {
            // four-byte character
            $ascii1 = ord($content[$i + 1]);
            $ascii2 = ord($content[$i + 2]);
            $ascii3 = ord($content[$i + 3]);
            $unicode = (15 & $ascii) * 262144 +
                    (63 & $ascii1) * 4096 +
                    (63 & $ascii2) * 64 +
                    (63 & $ascii3);
            $result .= "&#$unicode;";
            $i += 3;
        }
    }
    return $result;
}
/**
 * 格式化为XML
 *
 * @param string $content
 * @return mixed
 */
function xmlencode($content){
    if (strlen($content) == 0) return $content;
    return str_replace(
        array('&',"'",'"','>','<'),
        array('&amp;','&apos;','&quot;','&gt;','&lt;'),
        $content
    );
}
/**
 * XMLdecode
 *
 * @param string $content
 * @return mixed
 */
function xmldecode($content){
    if (strlen($content) == 0) return $content;
    return str_replace(
        array('&amp;','&apos;','&quot;','&gt;','&lt;'),
        array('&',"'",'"','>','<'),
        $content
    );
}
/**
 * 格式化大小
 *
 * @param int $bytes
 * @return string
 */
function format_size($bytes){
    if ($bytes == 0) return '-';
    $bytes = floatval($bytes);
    $units = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB');
    $i = 0; while ($bytes >= 1024) { $bytes /= 1024; $i++; }
    $precision = $i == 0 ? 0 : 2;
    return number_format(round($bytes, $precision), $precision) . $units[$i];
}
/**
 * array_splice 保留key
 *
 * @param array &$input
 * @param int $start
 * @param int $length
 * @param mixed $replacement
 * @return array|bool
 */
function array_ksplice(&$input, $start, $length=0, $replacement=null) {
    if (!is_array($replacement)) {
        return array_splice($input, $start, $length, $replacement);
    }
    $keys        = array_keys($input);
    $values      = array_values($input);
    $replacement = (array) $replacement;
    $rkeys       = array_keys($replacement);
    $rvalues     = array_values($replacement);
    array_splice($keys, $start, $length, $rkeys);
    array_splice($values, $start, $length, $rvalues);
    $input = array_combine($keys, $values);
    return $input;
}
/**
 * 递归地合并一个或多个数组
 *
 *
 * Merges any number of arrays / parameters recursively, replacing
 * entries with string keys with values from latter arrays.
 * If the entry or the next value to be assigned is an array, then it
 * automagically treats both arguments as an array.
 * Numeric entries are appended, not replaced, but only if they are
 * unique.
 *
 * @example:
 *  $result = array_merge_recursive_distinct($a1, $a2, ... $aN)
 *
 * @return array
 */
function array_merge_recursive_distinct() {
    $arrays = func_get_args();
    $base = array_shift($arrays);
    if (!is_array($base)) $base = empty($base) ? array() : array($base);
    foreach ($arrays as $append) {
        if (!is_array($append)) $append = array($append);
        foreach ($append as $key => $value) {
            if (!array_key_exists($key, $base) and !is_numeric($key)) {
                $base[$key] = $append[$key];
                continue;
            }
            if (is_array($value) or is_array($base[$key])) {
                $base[$key] = array_merge_recursive_distinct($base[$key], $append[$key]);
            } else if (is_numeric($key)) {
                if (!in_array($value, $base)) $base[] = $value;
            } else {
                $base[$key] = $value;
            }
        }
    }
    return $base;
}
/**
 * Hidden Real Path
 *
 * @param string $path
 * @return string
 */
function hide_path($path){
    $abs_path = str_replace(DIRECTORY_SEPARATOR, '/', APP_PATH . DIRECTORY_SEPARATOR);
    $src_path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
    return str_replace($abs_path, (IS_CLI ? '/' : APP_ROOT), $src_path);
}
/**
 * 批量创建目录
 *
 * @param string $path   文件夹路径
 * @param int    $mode   权限
 * @return bool
 */
function mkdirs($path, $mode = 0700){
    // sina sae 不能创建目录
    if (IS_SAE || strlen($path)==0) return false;
    if (!is_dir($path)) {
        mkdirs(dirname($path), $mode);
        $error_level = error_reporting(0);
        $result      = mkdir($path, $mode);
        error_reporting($error_level);
        return $result;
    }
    return true;
}
/**
 * 删除文件夹
 *
 * @param string $path		要删除的文件夹路径
 * @return bool
 */
function rmdirs($path){
    $error_level = error_reporting(0);
    if ($dh = opendir($path)) {
        while (false !== ($file=readdir($dh))) {
            if ($file != '.' && $file != '..') {
                $file_path = $path.'/'.$file;
                is_dir($file_path) ? rmdirs($file_path) : unlink($file_path);
            }
        }
        closedir($dh);
    }
    $result = rmdir($path);
    error_reporting($error_level);
    return $result;
}
/**
 * 自动转换字符集 支持数组转换
 *
 * @param string $from
 * @param string $to
 * @param mixed  $data
 * @return mixed
 */
function iconvs($from, $to, $data) {
    $from = strtoupper($from) == 'UTF8' ? 'UTF-8' : $from;
    $to = strtoupper($to) == 'UTF8' ? 'UTF-8' : $to;
    if (strtoupper($from) === strtoupper($to) || empty($data) || (is_scalar($data) && !is_string($data))) {
        //如果编码相同或者非字符串标量则不转换
        return $data;
    }
    if (is_string($data)) {
        if (function_exists('iconv')) {
            $to = substr($to, -8) == '//IGNORE' ? $to : $to . '//IGNORE';
            return iconv($from, $to, $data);
        } elseif (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($data, $to, $from);
        } else {
            return $data;
        }
    }
    elseif (is_array($data)) {
        foreach ($data as $key => $val) {
            $_key = iconvs($from, $to, $key);
            $data[$_key] = iconvs($from, $to, $val);
            if ($key != $_key) {
                unset($data[$key]);
            }
        }
        return $data;
    }
    else {
        return $data;
    }
}
/**
 * 清除空白
 *
 * @param string $content
 * @return string
 */
function clear_space($content){
    if (strlen($content)==0) return $content; $r = $content;
    $r = str_replace(array(chr(9),chr(10),chr(13)),'',$r);
    while (strpos($r,chr(32).chr(32))!==false || strpos($r,'&nbsp;')!==false) {
        $r = str_replace(chr(32).chr(32),chr(32),str_replace('&nbsp;',chr(32),$r));
    }
    return $r;
}
/**
 * 生成guid
 *
 * @param string $mix
 * @param string $hyphen
 * @return string
 */
function guid($mix=null, $hyphen = '-'){
    if (is_null($mix)) {
        $randid = uniqid(mt_rand(),true);
    } else {
        if (is_object($mix) && function_exists('spl_object_hash')) {
            $randid = spl_object_hash($mix);
        } elseif (is_resource($mix)) {
            $randid = get_resource_type($mix).strval($mix);
        } else {
            $randid = serialize($mix);
        }
    }
    $randid = strtoupper(md5($randid));
    $result = array();
    $result[] = substr($randid, 0, 8);
    $result[] = substr($randid, 8, 4);
    $result[] = substr($randid, 12, 4);
    $result[] = substr($randid, 16, 4);
    $result[] = substr($randid, 20, 12);
    return implode($hyphen, $result);
}
/**
 * 取得客户端的IP
 *
 * @return string
 */
function get_client_ip() {
    $ip = null;
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (!empty($_SERVER['HTTP_VIA '])) {
            $ip = $_SERVER['HTTP_VIA '];
        } else {
            $ip = 'Unknown';
        }
    }
    return $ip;
}
/**
 * 页面跳转
 *
 * @param string $url
 * @param int $status
 * @return void
 */
function redirect($url, $status=302) {
    if (is_xhr_request()) {
        quit(array( 'status'=> $status, 'location' => $url ));
    } else {
        if (!headers_sent()) header("Location: {$url}", true, $status);
        $html = '<!DOCTYPE html>';
        $html.= '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        $html.= '<meta http-equiv="refresh" content="0;url='.$url.'" />';
        $html.= '<title>'.$url.'</title>';
        $html.= '<script type="text/javascript" charset="utf-8">';
        $html.= 'self.location.replace("' . addcslashes($url, "'") . '");';
        $html.= '</script>';
        $html.= '</head><body></body></html>';
        quit($html);
    }
}
/**
 * 内容截取，支持正则
 *
 * $start,$end,$clear 支持正则表达式，“/”斜杠开头为正则模式
 * $clear 支持数组
 *
 * @param string $content           内容
 * @param string $start             开始代码
 * @param string $end               结束代码
 * @param string|array $clear      清除内容
 * @return string
 */
function mid($content, $start, $end = null, $clear = null) {
    if (empty($content) || empty($start)) return null;
    if ( strncmp($start, '/', 1) === 0) {
        if (preg_match($start, $content, $args)) {
            $start = $args[0];
        }
    }
    if ( $end && strncmp($end, '/', 1) === 0 ) {
        if (preg_match($end, $content, $args)) {
            $end = $args[0];
        }
    }
    $start_len = strlen($start); $result = null;
    $start_pos = stripos($content,$start); if ($start_pos === false) return null;
    $length    = $end===null ? null : stripos(substr($content,-(strlen($content)-$start_pos-$start_len)),$end);
    if ($start_pos !== false) {
        if ($length === null) {
            $result = trim(substr($content, $start_pos + $start_len));
        } else {
            $result = trim(substr($content, $start_pos + $start_len, $length));
        }
    }
    if ($result && $clear) {
        if (is_array($clear)) {
            foreach ($clear as $v) {
                if ( strncmp($v, '/', 1) === 0 ) {
                    $result = preg_replace($v, '', $result);
                } else {
                    if (strpos($result, $v) !== false) {
                        $result = str_replace($v, '', $result);
                    }
                }
            }
        } else {
            if ( strncmp($clear, '/', 1) === 0 ) {
                $result = preg_replace($clear, '', $result);
            } else {
                if (strpos($result,$clear) !== false) {
                    $result = str_replace($clear, '', $result);
                }
            }
        }
    }
    return $result;
}
/**
 * 格式化URL地址
 *
 * 补全url地址，方便采集
 *
 * @param string $base  页面地址
 * @param string $html  html代码
 * @return string
 */
function format_url($base, $html) {
    if (preg_match_all('/<(img|script)[^>]+src=([^\s]+)[^>]*>|<(a|link)[^>]+href=([^\s]+)[^>]*>/iU', $html, $matchs)) {
        $pase_url  = parse_url($base);
        $base_host = sprintf('%s://%s',   $pase_url['scheme'], $pase_url['host']);
        if (($pos=strpos($pase_url['path'], '#')) !== false) {
            $base_path = rtrim(dirname(substr($pase_url['path'], 0, $pos)), '\\/');
        } else {
            $base_path = rtrim(dirname($pase_url['path']), '\\/');
        }
        $base_url = $base_host.$base_path;
        foreach($matchs[0] as $match) {
            if (preg_match('/^(.+(href|src)=)([^ >]+)(.+?)$/i', $match, $args)) {
                $url = trim(trim($args[3],'"'),"'");
                // http 开头，跳过
                if (preg_match('/^(http|https|ftp)\:\/\//i', $url)) continue;
                // 邮件地址和javascript
                if (strncasecmp($url, 'mailto:', 7)===0 || strncasecmp($url, 'javascript:', 11)===0) continue;
                // 绝对路径
                if (strncmp($url, '/', 1) === 0) {
                    $url = $base_host.$url;
                }
                // 相对路径
                elseif (strncmp($url, '../', 3) === 0) {
                    while (strncmp($url, '../', 3) === 0) {
                        $url = substr($url, -(strlen($url)-3));
                        if(strlen($base_path) > 0){
                            $base_path = dirname($base_path);
                            if ($base_path=='/') $base_path = '';
                        }
                        if ($url == '../') {
                            $url = ''; break;
                        }
                    }
                    $url = $base_host.$base_path.'/'.$url;
                }
                // 当前路径
                elseif (strncmp($url, './', 2) === 0) {
                    $url = $base_url.'/'.substr($url, 2);
                }
                // 其他
                else {
                    $url = $base_url.'/'.$url;
                }
                // 替换标签
                $html = str_replace($match, sprintf('%s"%s"%s', $args[1], $url, $args[4]), $html);
            }
        }
    }
    return $html;
}

/**
 *
 * Compat 兼容函数
 *
 *******************************************************/
if (!function_exists('_')) {
    function _($text) {
        return __($text);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr( $str, $start, $length=null, $encoding='UTF-8' ) {
        if ( !in_array( $encoding, array('utf8','utf-8','UTF8','UTF-8') ) ) {
            return is_null( $length )? substr( $str, $start ) : substr( $str, $start, $length);
        }
        if (function_exists('iconv_substr')){
            return iconv_substr($str,$start,$length,$encoding);
        }
        // use the regex unicode support to separate the UTF-8 characters into an array
        preg_match_all( '/./us', $str, $match );
        $chars = is_null( $length )? array_slice( $match[0], $start ) : array_slice( $match[0], $start, $length );
        return implode( '', $chars );
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen( $str, $encoding='UTF-8' ) {
        if ( !in_array( $encoding, array('utf8','utf-8','UTF8','UTF-8')) ) {
            return strlen($str);
        }
        if (function_exists('iconv_strlen')){
            return iconv_strlen($str,$encoding);
        }
        // use the regex unicode support to separate the UTF-8 characters into an array
        preg_match_all( '/./us', $str, $match );
        return count($match);
    }
}

if (!function_exists('hash_hmac')) {
    function hash_hmac($algo, $data, $key, $raw_output = false) {
        $packs = array('md5' => 'H32', 'sha1' => 'H40');

        if (!isset($packs[$algo]))
            return false;

        $pack = $packs[$algo];

        if (strlen($key) > 64)
            $key = pack($pack, $algo($key));

        $key = str_pad($key, 64, chr(0));

        $ipad = (substr($key, 0, 64) ^ str_repeat(chr(0x36), 64));
        $opad = (substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64));

        $hmac = $algo($opad . pack($pack, $algo($ipad . $data)));

        if ($raw_output)
            return pack($pack, $hmac);
        return $hmac;
    }
}

if (!function_exists('gzinflate')) {
    /**
     * Decompression of deflated string while staying compatible with the majority of servers.
     *
     * Certain Servers will return deflated data with headers which PHP's gziniflate()
     * function cannot handle out of the box. The following function lifted from
     * http://au2.php.net/manual/en/function.gzinflate.php#77336 will attempt to deflate
     * the various return forms used.
     *
     * @param binary $gz_data
     * @return bool|string
     */
    function gzinflate($gz_data) {
        if ( !strncmp($gz_data, "\x1f\x8b\x08", 3) ) {
            $i = 10;
            $flg = ord( substr($gz_data, 3, 1) );
            if ( $flg > 0 ) {
                if ( $flg & 4 ) {
                    list($xlen) = unpack('v', substr($gz_data, $i, 2) );
                    $i = $i + 2 + $xlen;
                }
                if ( $flg & 8 )
                    $i = strpos($gz_data, "\0", $i) + 1;
                if ( $flg & 16 )
                    $i = strpos($gz_data, "\0", $i) + 1;
                if ( $flg & 2 )
                    $i = $i + 2;
            }
            return gzinflate( substr($gz_data, $i, -8) );
        } else {
            return false;
        }
    }
}
if (!function_exists('gzdecode')) {
    /**
     * Opposite of gzencode. Decodes a gzip'ed file.
     *
     * @param string $data  compressed data
     * @return bool|null|string True if the creation was successfully
     */
    function gzdecode($data) {
        $len = strlen($data);
        if ($len < 18 || strncmp($data,"\x1f\x8b",2)) {
            return false;  // Not GZIP format (See RFC 1952)
        }
        $method = ord(substr($data,2,1));  // Compression method
        $flags  = ord(substr($data,3,1));  // Flags
        if ($flags & 31 != $flags) {
            // Reserved bits are set -- NOT ALLOWED by RFC 1952
            return false;
        }
        // NOTE: $mtime may be negative (PHP integer limitations)
        $mtime = unpack("V", substr($data,4,4));
        $mtime = $mtime[1];
        $xfl   = substr($data,8,1);
        $os    = substr($data,8,1);
        $headerlen = 10;
        $extralen  = 0;
        $extra     = "";
        if ($flags & 4) {
            // 2-byte length prefixed EXTRA data in header
            if ($len - $headerlen - 2 < 8) {
                return false;    // Invalid format
            }
            $extralen = unpack("v",substr($data,8,2));
            $extralen = $extralen[1];
            if ($len - $headerlen - 2 - $extralen < 8) {
                return false;    // Invalid format
            }
            $extra = substr($data,10,$extralen);
            $headerlen += 2 + $extralen;
        }

        $filenamelen = 0;
        $filename = "";
        if ($flags & 8) {
            // C-style string file NAME data in header
            if ($len - $headerlen - 1 < 8) {
                return false;    // Invalid format
            }
            $filenamelen = strpos(substr($data,8+$extralen),chr(0));
            if ($filenamelen === false || $len - $headerlen - $filenamelen - 1 < 8) {
                return false;    // Invalid format
            }
            $filename  = substr($data,$headerlen,$filenamelen);
            $headerlen+= $filenamelen + 1;
        }

        $commentlen = 0;
        $comment = "";
        if ($flags & 16) {
            // C-style string COMMENT data in header
            if ($len - $headerlen - 1 < 8) {
                return false;    // Invalid format
            }
            $commentlen = strpos(substr($data,8+$extralen+$filenamelen),chr(0));
            if ($commentlen === false || $len - $headerlen - $commentlen - 1 < 8) {
                return false;    // Invalid header format
            }
            $comment   = substr($data,$headerlen,$commentlen);
            $headerlen+= $commentlen + 1;
        }

        $headercrc = "";
        if ($flags & 1) {
            // 2-bytes (lowest order) of CRC32 on header present
            if ($len - $headerlen - 2 < 8) {
                return false;    // Invalid format
            }
            $calccrc   = crc32(substr($data,0,$headerlen)) & 0xffff;
            $headercrc = unpack("v", substr($data,$headerlen,2));
            $headercrc = $headercrc[1];
            if ($headercrc != $calccrc) {
                return false;    // Bad header CRC
            }
            $headerlen += 2;
        }

        // GZIP FOOTER - These be negative due to PHP's limitations
        $datacrc = unpack("V",substr($data,-8,4));
        $datacrc = $datacrc[1];
        $isize   = unpack("V",substr($data,-4));
        $isize   = $isize[1];

        // Perform the decompression:
        $bodylen = $len-$headerlen-8;
        if ($bodylen < 1) {
            // This should never happen - IMPLEMENTATION BUG!
            return null;
        }
        $body = substr($data,$headerlen,$bodylen);
        $data = "";
        if ($bodylen > 0) {
            switch ($method) {
                case 8:
                // Currently the only supported compression method:
                $data = gzinflate($body);
                break;
                default:
                // Unknown compression method
                return false;
            }
        } else {
            // I'm not sure if zero-byte body content is allowed.
            // Allow it for now...  Do nothing...
        }

        // Verifiy decompressed size and CRC32:
        // NOTE: This may fail with large data sizes depending on how
        //      PHP's integer limitations affect strlen() since $isize
        //      may be negative for large sizes.
        if ($isize != strlen($data) || crc32($data) != $datacrc) {
            // Bad format!  Length or CRC doesn't match!
            return false;
        }
        return $data;
    }
}

if (!function_exists('image_type_to_extension')) {
    /**
     * Get file extension for image type
     *
     * @param int $imagetype
     * @param bool $include_dot
     * @return bool|string
     */
    function image_type_to_extension($imagetype, $include_dot=true) {
        if (empty($imagetype)) return false;
        $dot = $include_dot ? '.' : '';
        switch ($imagetype) {
            case IMAGETYPE_GIF       : return $dot.'gif';
            case IMAGETYPE_JPEG      : return $dot.'jpg';
            case IMAGETYPE_PNG       : return $dot.'png';
            case IMAGETYPE_SWF       : return $dot.'swf';
            case IMAGETYPE_PSD       : return $dot.'psd';
            case IMAGETYPE_BMP       : return $dot.'bmp';
            case IMAGETYPE_TIFF_II   : return $dot.'tiff';
            case IMAGETYPE_TIFF_MM   : return $dot.'tiff';
            case IMAGETYPE_JPC       : return $dot.'jpc';
            case IMAGETYPE_JP2       : return $dot.'jp2';
            case IMAGETYPE_JPX       : return $dot.'jpf';
            case IMAGETYPE_JB2       : return $dot.'jb2';
            case IMAGETYPE_SWC       : return $dot.'swc';
            case IMAGETYPE_IFF       : return $dot.'aiff';
            case IMAGETYPE_WBMP      : return $dot.'wbmp';
            case IMAGETYPE_XBM       : return $dot.'xbm';
            case IMAGETYPE_ICO       : return $dot.'ico';
            default                  : return false;
        }
    }
}
/**
 * Detect MIME Content-type for a file (deprecated)
 *
 * @param string $filename
 * @return string
 */
function file_mime_type($filename) {
    if (is_ifile($filename) && function_exists('finfo_open')) {
        $finfo    = finfo_open(FILEINFO_MIME);
        $mimetype = finfo_file($finfo, $filename);
        finfo_close($finfo);
        return $mimetype;
    } else if(is_ifile($filename) && function_exists('mime_content_type')) {
        return mime_content_type($filename);
    } else {
        switch (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            case 'txt':
                return 'text/plain';
            case 'htm': case 'html': case 'php':
                return 'text/html';
            case 'css':
                return 'text/css';
            case 'js':
                return 'application/javascript';
            case 'json':
                return 'application/json';
            case 'xml':
                return 'application/xml';
            case 'swf':
                return 'application/x-shockwave-flash';
            case 'flv':
                return 'video/x-flv';

            // images
            case 'png':
                return 'image/png';
            case 'jpe': case 'jpg': case 'jpeg':
                return 'image/jpeg';
            case 'gif':
                return 'image/gif';
            case 'bmp':
                return 'image/bmp';
            case 'ico':
                return 'image/x-icon';
            case 'tiff': case 'tif':
                return 'image/tiff';
            case 'svg': case 'svgz':
                return 'image/svg+xml';

            // archives
            case 'zip':
                return 'application/zip';
            case 'rar':
                return 'application/rar';
            case 'exe': case 'cpt': case 'bat': case 'dll':
                return 'application/x-msdos-program';
            case 'msi':
                return 'application/x-msi';
            case 'cab':
                return 'application/x-cab';
            case 'qtl':
                return 'application/x-quicktimeplayer';

            // audio/video
            case 'mp3': case 'mpga': case 'mpega': case 'mp2': case 'm4a':
                return 'audio/mpeg';
            case 'qt': case 'mov':
                return 'video/quicktime';
            case 'mpeg': case 'mpg': case 'mpe':
                return 'video/mpeg';
            case '3gp':
                return 'video/3gpp';
            case 'mp4':
                return 'video/mp4';

            // adobe
            case 'pdf':
                return 'application/pdf';
            case 'psd':
                return 'image/x-photoshop';
            case 'ai': case 'ps': case 'eps': case 'epsi': case 'epsf': case 'eps2': case 'eps3':
                return 'application/postscript';
            case 'psd':
                return 'image/x-photoshop';

            // ms office
            case 'doc': case 'dot':
                return 'application/msword';
            case 'rtf':
                return 'application/rtf';
            case 'xls': case 'xlb': case 'xlt':
                return 'application/vnd.ms-excel';
            case 'ppt': case 'pps':
                return 'application/vnd.ms-powerpoint';
            case 'xlsx':
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            case 'xltx':
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.template';
            case 'pptx':
                return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            case 'ppsx':
                return 'application/vnd.openxmlformats-officedocument.presentationml.slideshow';
            case 'potx':
                return 'application/vnd.openxmlformats-officedocument.presentationml.template';
            case 'docx':
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            case 'dotx':
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.template';

            // open office
            case 'odt':
                return 'application/vnd.oasis.opendocument.text';
            case 'ods':
                return 'application/vnd.oasis.opendocument.spreadsheet';
            case 'odp':
                return 'application/vnd.oasis.opendocument.presentation';
            case 'odb':
                return 'application/vnd.oasis.opendocument.database';
            case 'odg':
                return 'application/vnd.oasis.opendocument.graphics';
            case 'odi':
                return 'application/vnd.oasis.opendocument.image';

            default:
                return 'application/octet-stream';
        }
    }
}


if (!function_exists('json_encode')) {
    function json_encode($string) {
        global $upf_json;
        if (get_class($upf_json) != 'Services_JSON') {
            $upf_json = new Services_JSON();
        }
        return $upf_json->encode($string);
    }
}

if (!function_exists('json_decode')) {
    function json_decode($string, $assoc_array = false) {
        global $upf_json;

        if (get_class($upf_json) != 'Services_JSON') {
            $upf_json = new Services_JSON();
        }

        $res = $upf_json->decode($string);
        if ($assoc_array)
            $res = _json_decode_object_helper($res);
        return $res;
    }

    function _json_decode_object_helper($data) {
        if (is_object($data))
            $data = get_object_vars($data);
        return is_array($data) ? array_map(__FUNCTION__, $data) : $data;
    }
}

if (!function_exists('spyc_load')) {
    /**
     * Parses YAML to array.
     * @param string $string YAML string.
     * @return array
     */
    function spyc_load($string) {
        return Spyc::YAMLLoadString($string);
    }
}

if (!function_exists('spyc_load_file')) {
    /**
     * Parses YAML to array.
     * @param string $file Path to YAML file.
     * @return array
     */
    function spyc_load_file($file) {
        return Spyc::YAMLLoad($file);
    }
}
