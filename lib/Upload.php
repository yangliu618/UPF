<?php
// 上传的文件太大
define('UPLOAD_ERR_MAX_SIZE',       5);
// 没有权限写入文件
defined('UPLOAD_ERR_CANT_WRITE') or define('UPLOAD_ERR_CANT_WRITE',    7);
// A PHP extension stopped the file upload
defined('UPLOAD_ERR_EXTENSION') or define('UPLOAD_ERR_EXTENSION',      8);
// 上传的文件类型不允许
define('UPLOAD_ERR_FORBID_EXT',     9);
// POST值超过了 post_max_size
define('UPLOAD_ERR_POST_MAXSIZE',   10);
// 未知异常
define('UPLOAD_ERR_UNKNOWN',        999);
// 默认的临时目录
defined('UPLOAD_TMP_PATH') or define('UPLOAD_TMP_PATH', TMP_PATH);

/**
 * 文件上传类
 *
 * @author  Lukin <my@lukin.cn>
 * @version $Id$
 */
class Upload{
    // 单个文件大小，0:为不限制
    public $max_size = 0;
    // 保存路径
    public $save_path;
    // 允许的文件后缀
    public $allow_exts;
    // 错误信息
    private $error = 0;
        
    public function __construct(){
        // 判断总大小
        if (intval(get_cfg_var('post_max_size')) < ($_SERVER['CONTENT_LENGTH']/1024/1024)) {
            $this->error = UPLOAD_ERR_POST_MAXSIZE;
        }
        // HTML5上传
        if (isset($_SERVER['HTTP_CONTENT_DISPOSITION'])) {
            if (preg_match('/attachment;\s+name="(.+?)";\s+filename="(.+?)"/i', $_SERVER['HTTP_CONTENT_DISPOSITION'], $info)) {
                $error_level = error_reporting(0);
                $temp_dir  = ini_get('upload_tmp_dir');
                $temp_name = rtrim(($temp_dir == '' ? UPLOAD_TMP_PATH : $temp_dir), '/') . '/' . microtime(true) . mt_rand(1, 9999);
                file_put_contents($temp_name, file_get_contents('php://input'));
                $_FILES[$info[1]] = array(
                    'name'      => $info[2],
                    'tmp_name'  => $temp_name,
                    'size'      => filesize($temp_name),
                    'sha1'      => sha1_file($temp_name),
                    'type'      => file_mime_type($info[2]),
                    'html5'     => true,
                    'error'     => 0,
                );
                error_reporting($error_level);
            }
        }
    }
    /**
     * 保存文件
     *
     * @param string $name
     * @param string $toname
     * @return bool
     */
    public function save($name, $toname=null) {
        if (!isset($_FILES[$name])) return false;
        $info = $_FILES[$name];
        // 返回错误信息
        if ($info['error'] > 0) {
            $this->error = $info['error'];
            return false;
        }
        // 检查文件大小
        if (0 < $this->max_size && intval($info['size']) > intval($this->max_size)) {
            $this->error = UPLOAD_ERR_MAX_SIZE;
            return false;
        }

        $info['ext']  = strtolower(pathinfo($info['name'], PATHINFO_EXTENSION));
        // 检查文件后缀
        if ($this->allow_exts && $this->allow_exts != '*' && !instr($info['ext'], strtolower($this->allow_exts))) {
            $this->error = UPLOAD_ERR_FORBID_EXT;
            return false;
        }
        $info['sha1'] = sha1_file($info['tmp_name']);
        $info['name'] = $toname ? $toname : rawurldecode(basename(pathinfo($info['name'], PATHINFO_BASENAME), '.' . $info['ext']));
        $info['path'] = rtrim($this->save_path, '/') . '/' . $info['sha1'] . '.' . $info['ext'];
        // 移动文件
        $error_level = error_reporting(0);
        if (IS_SAE) {
            $move_file = true;
        } else {
            mkdirs(dirname($info['path']));
            $move_file = $info['html5'] ? rename($info['tmp_name'], $info['path']) : move_uploaded_file($info['tmp_name'], $info['path']);
        }
        error_reporting($error_level);
        if ($move_file){
            return $info;
        } else {
            $this->error = UPLOAD_ERR_CANT_WRITE;
            return false;
        }
    }
    /**
     * 解析路径
     *
     * @param string $target
     * @return array
     */
    public static function sae_parse($target) {
        $app_path = dirname(UPF_PATH);
        if (strncmp($target, $app_path, strlen($app_path)) === 0) {
            $target = substr($target, strlen($app_path)+1);
        }
        $index    = strpos($target, '/');
        $domain   = substr($target, 0, $index);
        $filename = substr($target, $index+1);
        return array($domain, $filename);
    }
    /**
     * 返回错误信息
     *
     * @return string
     */
    public function error() {
        $errors = array(
            1 => __('上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值。'),
            2 => __('上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值。'),
            3 => __('文件只有部分被上传。'),
            4 => __('没有文件被上传。'),
            5 => sprintf(__('上传的文件大小超过了限制(%s)。'), format_size($this->max_size)),
            6 => __('找不到临时文件夹。'),
            7 => __('文件写入失败。'),
            8 => __('PHP扩展停止了文件上传。'),
            9 => __('上传的文件类型是不允许的。'),
            10 => sprintf(__('上传文件的总大小超过了限制(%s)。'), get_cfg_var('post_max_size')),
        );
        if ($this->error ==0 ) return '';
        return isset($errors[$this->error]) ? $errors[$this->error] : __('未知错误');
    }
    /**
     * 错误代码
     *
     * @return int
     */
    public function errno() {
        return $this->error;
    }
}
