<?php
// 默认的缓存目录
defined('FCACHE_PATH') or define('FCACHE_PATH', TMP_PATH . '/fcache');
// 默认过期时间
defined('FCACHE_EXPIRE') or define('FCACHE_EXPIRE', 31536000);
/**
 * 文件缓存类
 *
 * @author  Lukin <my@lukin.cn>
 * @version $Id$
 */
class FCache {
    /**
     * 魔术方法 用来兼容
     *
     * @param string $name
     * @param array $arguments
     * @return null
     */
    public function __call($name, $arguments) {
        return null;
    }
    /**
     * 取得缓存路径
     *
     * @param string $key
     * @return string
     */
    public static function file($key) {
        $md5_key = md5($key); $folders = array();
        for ($i = 1; $i <= 3; $i++) $folders[] = substr($md5_key, 0, $i);
        $file = sprintf('%s/%s/%s.cache', FCACHE_PATH, implode('/', $folders), $md5_key);
        $floder = dirname($file); mkdirs($floder);
        return $file;
    }
    /**
     * 添加一个值，如果已经存在，则覆盖
     *
     * @param string $key
     * @param mixed $data
     * @param int $expire   过期时间，单位：秒
     * @return bool
     */
    public function set($key, $data, $expire=0) {
        $result      = false;
        $hash_file   = self::file($key);
        $error_level = error_reporting(0);
        $fp = fopen($hash_file, 'wb');
        if ($fp) {
            // 延长过期时间，防止高并发情况出现
            touch($hash_file, time() + 30);
            flock($fp, LOCK_EX);
            $mqr = get_magic_quotes_runtime();
            if ($mqr) set_magic_quotes_runtime(0);
            if ($data === null) $data = new NOOPClass();
            // 判断是否需要序列化
            if (!is_scalar($data)) {
                $data = serialize($data);
            }
            fwrite($fp, $data);
            if ($mqr) set_magic_quotes_runtime($mqr);
            flock($fp, LOCK_UN);
            fclose($fp);
            // 默认永不过期
            $expire = $expire===0 ? FCACHE_EXPIRE : $expire;
            // 写入过期时间
            touch($hash_file, time() + abs($expire));
                        
            $result = true;
        }
        error_reporting($error_level);
        return $result;
    }
    /**
     * 取得一个缓存结果
     *
     * @param string $key
     * @return mixed|string
     */
    public function get($key) {
        $data        = null;
        $hash_file   = self::file($key);
        $error_level = error_reporting(0);
        if (is_file($hash_file)) {
            $fp = fopen($hash_file, "rb");
            flock($fp, LOCK_SH);
            if ($fp) {
                clearstatcache();
                $length = filesize($hash_file);
                $mqr = get_magic_quotes_runtime();
                if ($mqr) set_magic_quotes_runtime(0);
                if ($length) {
                    $data = fread($fp, $length);
                } else {
                    $data = '';
                }
                if ($mqr) set_magic_quotes_runtime($mqr);
                flock($fp, LOCK_UN);
                fclose($fp);

                if (is_serialized($data)) {
                    $data = unserialize($data);
                }
                // 检查文件是否过期
                $last_time = filemtime($hash_file);
                if ($last_time < time()) {
                    unlink($hash_file);
                }
            }
        }
        error_reporting($error_level);
        return $data;
    }
    /**
     * 删除一个key值
     *
     * @param string $key
     * @return bool
     */
    public function delete($key) {
        $hash_file = self::file($key);
        if (is_file($hash_file)) {
            $error_level = error_reporting(0);
            unlink($hash_file);
            error_reporting($error_level);
            return true;
        }
        return false;
    }
    /**
     * 清除所有缓存的数据
     *
     * @return bool
     */
    public function flush() {
        return rmdirs(FCACHE_PATH);
    }
}
