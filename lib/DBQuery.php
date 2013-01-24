<?php
/**
 * 数据库连接类
 *
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2011-08-13 15:19
 */
abstract class DBQuery {
    public $ready      = false;
    public $conn       = null;
    public $last_sql   = '';
    public $name       = 'test';
    public $prefix     = '';
    public $scheme     = null;
    public $query_count = 0;

    /**
     * 类构造
     *
     * @abstract
     * @param array $config
     */
    abstract public function __construct($config);
    /**
     * SQL查询
     *
     * @abstract
     * @param string $sql
     * @return mixed
     */
    abstract public function query($sql);
    /**
     * 判断数据库是否存在
     *
     * @abstract
     * @param string $dbname
     * @return bool
     */
    abstract public function is_database($dbname);
    /**
     * 判断表是否存在
     *
     * @abstract
     * @param string $table
     * @return bool
     */
    abstract public function is_table($table);
    /**
     * 列出表里的字段
     *
     * @abstract
     * @param string $table
     * @return array
     */
    abstract public function list_fields($table);
    /**
     * 获取自增ID
     *
     * @abstract
     * @param string $table
     * @return int
     */
    abstract public function autoindex($table);
    /**
     * mysql结果
     *
     * @abstract
     * @param resource $result
     * @param int $mode
     * @return array
     */
    abstract public function fetch($result, $mode=1);
    /**
     * 转义字符串
     *
     * @abstract
     * @param mixed $value
     * @return string
     */
    abstract public function escape($value);
    /**
     * 服务器版本
     *
     * @abstract
     * @return string
     */
    abstract public function version();
    /**
     * 关闭链接
     *
     * @abstract
     * @return void
     */
    abstract public function close();
    /**
     * 取得数据库实例
     *
     * @static
     * @param string $DSN
     * @param string $user
     * @param string $pwd
     * @return DBQuery
     */
    public static function &factory($DSN, $user=null, $pwd=null) {
        $config = array(); $scheme = $db = null;
        if (($pos=strpos($DSN, ':')) !== false) {
            $scheme = strtolower(substr($DSN, 0, $pos));
            $string = substr($DSN, $pos+1);
            if (strpos($string,';') !== false) {
                $arrays = explode(';', trim($string,';'));
                foreach ($arrays as $v) {
                    $pos = strpos($v, '=');
                    $key = trim(substr($v, 0, $pos));
                    $val = trim(substr($v, $pos + 1));
                    $config[$key] = $val;
                }
            } else {
                $config[] = $string;
            }
            if ($scheme && !isset($config['scheme']))
                $config['scheme'] = $scheme;
            if ($user!==null && !isset($config['user']))
                $config['user'] = $user;
            if ($pwd!==null && !isset($config['pwd']))
                $config['pwd'] = $pwd;
        }
        if ($scheme && $config) {
            // 加载数据库文件
            if (strncasecmp($scheme, 'pdo_sqlite', 10) === 0) {
                $classname = 'DB_PDO_SQLite';
            } else {
                $classname = 'DB_'.str_ireplace(array('my', 'sql'), array('My', 'SQL'), $scheme);
            }
            if (class_exists($classname)) {
                $db = new $classname($config);
            } else {
                $db = new NOOPClass();
            }
            $db->scheme = $scheme;
            if (isset($config['prefix'])) $db->prefix = $config['prefix'];
        }
        return $db;
    }
    /**
     * 等同于 mysql_result
     *
     * @param string $sql
     * @param int $row      偏移量
     * @return mixed|null
     */
    public function result($sql,$row=0) {
        $result = $this->query($sql);
        if ($rs = $this->fetch($result,0)) {
            return $rs[$row];
        }
        return null;
    }
    /**
     * 插入数据
     *
     * @param string $table
     * @param array $data   插入数据的数组，key对应列名，value对应值
     * @return int|null
     */
    public function insert($table, $data) {
        $cols = array();
        $vals = array();
        foreach ($data as $col => $val) {
            $cols[] = $this->identifier($col);
            $vals[] = $this->escape($val);
        }

        $sql = "insert into "
             . $this->identifier($table)
             . ' (' . implode(', ', $cols) . ') '
             . "values ('" . implode("', '", $vals) . "')";

             return $this->query($sql);
    }
    /**
     * 更新数据
     *
     * @param string $table
     * @param array $sets
     * @param mixed $where   where语句，支持数组，数组默认使用 AND 连接
     * @return int|null
     */
    public function update($table, $sets, $where = null) {
        // extract and quote col names from the array keys
        $set = array();
        foreach ($sets as $col => $val) {
            $val = $this->escape($val);
            if (substr($col,-1) == '+') {
                $col   = $this->identifier(rtrim($col, '+'));
                $set[] = $col." = ".$col." + '".$val."'";
            } elseif(substr($col,-1) == '-') {
                $col   = $this->identifier(rtrim($col, '-'));
                $set[] = $col." = ".$col." - '".$val."'";
            } else {
                $set[] = $this->identifier($col)." = '".$val."'";
            }
        }
        $where = $this->where($where);
        // build the statement
        $sql = "update "
             . $this->identifier($table)
             . ' set ' . implode(', ', $set)
             . (($where) ? " where {$where}" : '');

        return $this->query($sql);
    }
    /**
     * 删除数据
     *
     * @param string $table
     * @param mixed $where
     * @return int|null
     */
    public function delete($table, $where = null) {
        $where = $this->where($where);
        // build the statement
        $sql = "delete from "
             . $this->identifier($table)
             . (($where) ? " where {$where}" : '');

        return $this->query($sql);
    }
    /**
     * 判断列名是否存在
     *
     * @param string $table
     * @param string $field
     * @return bool
     */
    public function is_field($table, $field) {
        return in_array($field, $this->list_fields($table));
    }
    /**
     * SQL 预处理
     * 
     * @param string $query
     * @return mixed|null|string
     */
    protected function prepare($query = null) { // ( $query, *$args )
        if ( is_null( $query ) ) return '';
        $args = func_get_args(); array_shift( $args );
        // 预处理SQL
        if (preg_match_all("/'[^']+'|\"[^\"]+\"/", $query, $r)) {
            foreach ($r[0] as $i => $v) {
                $query = preg_replace('/' . preg_quote($v, '/') . '/', "'@{$i}@'", $query, 1);
            }
        }
        // 替换前缀
        $query = preg_replace('/#@_([^ ]+?)/i', $this->prefix . '$1', $query);
        // sqlite 处理转义符
        if (strpos($this->scheme, 'sqlite') !== false) {
            if (preg_match_all("/`[^`]+`/", $query, $r)) {
                foreach ($r[0] as $v) {
                    $query = preg_replace('/' . preg_quote($v, '/') . '/', "[".trim($v, '`')."]", $query, 1);
                }
            }
        }
        // 还原SQL
        if (isset($r[0]) && !empty($r[0])) {
            foreach ($r[0] as $i => $v) {
                $query = str_replace("'@{$i}@'", $v, $query);
            }
        }
        // 处理占位符
        if ($args) {
            if (preg_match('/\$[\d]+/', $query)) {
                while (preg_match('/\$[\d]+/', $query, $r, PREG_OFFSET_CAPTURE)) {
                    $v = $r[0];
                    $n = ltrim($v[0], '$');
                    if (array_key_exists($n, $args)) {
                        $query = substr_replace($query, $this->escape($args[$n]), $v[1], strlen($v[0]));
                    }
                }
            } else {
                $i = 0; $count = count($args);
                while ($count > 0) {
                    if (($pos=strpos($query, '?')) !== false) {
                        $query = substr_replace($query, $this->escape($args[$i]), $pos, 1);
                        --$count; $i++;
                    } else {
                        break;
                    }
                }
            }
        }
        return $query;
    }
    /**
     * where语句组合
     *
     * @param mixed $data
     * @return string
     */
    protected function where($data) {
        if (empty($data)) {
            return '';
        }
        if (is_string($data)) {
            return $data;
        }
        if (is_assoc($data)) {
            $cond = array();
            foreach ($data as $field => $value) {
                $cond[] = "(" . $this->identifier($field) ." = '". $this->escape($value) . "')";
            }
        } else {
            $cond = $data;
        }
        $sql = implode(' and ', $cond);
        return $sql;
    }
    /**
     * 转义变量
     *
     * @param mixed $value
     * @return string
     */
    protected function envalue($value) {
        // 空
        if ($value === null) return '';
        // 不是标量
        if (!is_scalar($value)) {
            // 是数组列表
            if (is_array($value) && !is_assoc($value)) {
                $value = implode(',', $value);
            }
            // 需要序列化
            else {
                $value = serialize($value);
            }
        }
        return $value;
    }
    /**
     * 转义SQL关键字
     *
     * @param string $filed
     * @return string
     */
    protected function identifier($filed){
        $result = null;
        // 检测是否是多个字段
        if (strpos($filed,',') !== false) {
            // 多个字段，递归执行
            $fileds = explode(',',$filed);
            foreach ($fileds as $v) {
                if (empty($result)) {
                    $result = $this->identifier($v);
                } else {
                    $result.= ','.$this->identifier($v);
                }
            }
            return $result;
        } else {
            $is_sqlite = strpos($this->scheme, 'sqlite') !== false;
            // 解析各个字段
            if (strpos($filed,'.') !== false) {
                $fileds = explode('.',$filed);
                $_table = trim($fileds[0]);
                $_filed = trim($fileds[1]);
                $_as    = chr(32).'as'.chr(32);
                if (stripos($_filed,$_as) !== false) {
                    $_filed = sprintf(($is_sqlite ? '[%s]%s[%s]' : '`%s`%s`%s`'),trim(substr($_filed,0,stripos($_filed,$_as))),$_as,trim(substr($_filed,stripos($_filed,$_as)+4)));
                }
                return sprintf($is_sqlite ? '[%s].%s' : '`%s`.%s', $_table, $_filed);
            } else {
                return sprintf($is_sqlite ? '[%s]' : '`%s`', $filed);
            }
        }
    }
    /**
     * 批量执行 SQL
     *
     * @param string $batSQL
     * @return bool
     */
    public function batch($batSQL) {
        if (!$batSQL) return false;
        $batSQL = str_replace("\r\n", "\n", $batSQL);
        $lines  = explode("\n", $batSQL);
        $sql    = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/;$/', $line)) {
                $sql.= $line;
                // 执行SQL
                $this->query($sql);
                // 执行完，置空
                $sql = '';
            } elseif (!preg_match('/^\-\-/', $line)     // --
                    && !preg_match('/^\/\//', $line)    // //
                    && !preg_match('/^\/\*/', $line)    // /*
                    && !preg_match('/^#/', $line)) {    // #
                if ($pos=strpos($line,'# ') !== false) {
                    $str = trim(substr($line, 0, $pos));
                    if (substr($str, -1) == ',') $line = $str;
                }
                $sql.= $line."\n";
            }
        }
        return true;
    }
    /**
     * FROM_UNIXTIME
     *
     * @param int $timestamp
     * @param string $format
     * @return string
     */
    public static function strftime($timestamp, $format) {
        return strftime($format, $timestamp);
    }
    /**
     * 列表截取
     *
     * @static
     * @param string $list  1,2,3,4
     * @param int $offset
     * @param int $length
     * @return string
     */
    public static function list_slice($list, $offset, $length) {
        return implode(',', array_slice(explode(',', $list), $offset, $length));
    }
    /**
     * 类析构
     *
     * @return void
     */
    public function __destruct(){
        $this->close();
    }
}
