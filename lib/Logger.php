<?php
/**
 * 日志类
 *
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2011-09-18 01:20
 */
define('LOGGER_OFF',    0); // Nothing at all.
define('LOGGER_DEBUG',  1); // Most Verbose
define('LOGGER_INFO',   2); // ...
define('LOGGER_WARN',   3); // ...
define('LOGGER_ERROR',  4); // ...
define('LOGGER_FATAL',  5); // ...
define('LOGGER_LOG',    6); // Least Verbose

class Logger {

    private $format	= 'Y-m-d H:i:s';
    private $queue  = array();

    private $priority;
    private $allowIPs, $isAllowed;
        
    // Logger instance
    private static $instance;
    /**
     * Returns Logger instance.
     *
     * @static
     * @return Logger
     */
    public static function &instance() {
        if (!self::$instance) {
            $level = get_config('logger_level');
            if (!$level) {
                if (isset($_GET['debug'])) {
                    $level = $_GET['debug']; Cookie::set('debug', $level);
                } else {
                    $level = Cookie::get('debug');
                }
                if (!$level) $level = LOGGER_OFF;
            }
            self::$instance = new Logger($level, get_config('logger_allowIPs'));
        }
        return self::$instance;
    }

    public function __construct($priority, $allowIPs = null) {
        $this->priority = $priority;
        $this->allowIPs = array(
            '127.0.0.0/127.255.255.255',
            '192.168.0.0/192.168.255.255',
            '10.0.0.0.0/10.255.255.255',
            '172.16.0.0/172.31.255.255',
        );
        if (is_array($allowIPs)) {
            $this->allowIPs = array_merge($this->allowIPs, $allowIPs);
        } elseif (is_string($allowIPs)) {
            array_unshift($this->allowIPs, $allowIPs);
        }
        $this->isAllowed = $this->isAllowed();
        
        App::instance()->register_shutdown(array(&$this, 'trace'));
        
    }
    /**
     * 判断IP是否在可以debug的范围内
     *
     * @return bool
     */
    private function isAllowed() {
        $strIP = get_client_ip();
        if ($strIP == 'Unknown') return false;
        $intIP = sprintf('%u', ip2long($strIP));
        foreach($this->allowIPs as $IPs) {
            if (strpos($IPs, '/') !== false) {
                $IPs = explode('/', $IPs);
            } else {
                $IPs = array($IPs, $IPs);
            }
            $IPs[0] = sprintf('%u', ip2long($IPs[0]));
            $IPs[1] = sprintf('%u', ip2long($IPs[1]));
            if ($IPs[0] <= $intIP && $intIP <= $IPs[1]) {
                return true;
            }
        }
        return false;
    }
        
    public function trace() {
        if (is_xhr_request()) {
            $logs = explode("\n", implode('', $this->queue));
            foreach($logs as $i=>$log) {
                $log = rtrim($log);
                if (mb_strlen($log,'UTF-8') > 255) {
                    $log = mb_substr($log, 0, 255).'...';
                }
                header('X-UPF-TRACE-'.$i.': '.$log);
            }
        } else {
            foreach($this->queue as $log) {
                e(nl2br($log));
            }
        }
    }

    public function info($line) {
        $this->log($line, LOGGER_INFO);
    }

    public function debug($line) {
        $this->log($line, LOGGER_DEBUG);
    }

    public function warn($line) {
        $this->log($line, LOGGER_WARN);
    }

    public function error($line) {
        $this->log($line, LOGGER_ERROR);
    }

    public function fatal($line) {
        $this->log($line, LOGGER_FATAL);
    }

    public function log($line, $priority = LOGGER_LOG) {
        if ($this->isAllowed && $this->priority <= $priority && $this->priority != LOGGER_OFF) {
            $time = date($this->format);

            switch ($priority) {
                case LOGGER_INFO:
                    $status = $time.' - [INFO]  --> '; break;
                case LOGGER_WARN:
                    $status = $time.' - [WARN]  --> '; break;
                case LOGGER_DEBUG:
                    $status = $time.' - [DEBUG] --> '; break;
                case LOGGER_ERROR:
                    $status = $time.' - [ERROR] --> '; break;
                case LOGGER_FATAL:
                    $status = $time.' - [FATAL] --> '; break;
                default:
                    $status = $time.' - [LOG]   --> '; break;
            }

            $this->queue[] = $status.$line."\n";
        }
    }
}
