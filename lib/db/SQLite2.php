<?php
/**
 * sqlite2 访问类
 *
 * @author  Lukin <my@lukin.cn>
 * @version $Id$
 */
class DB_SQLite2 extends DBQuery {
    // 持久链接
    private $pconnect = false;

    /**
     * 初始化链接
     *
     * @param array $config
     */
    public function __construct($config) {
        if (!function_exists('sqlite_query')) {
            upf_error(sprintf(__('您的 PHP 似乎缺少所需的 %s 扩展。'), 'SQLite2'));
        }
        if (!empty($config)) {
            $this->name     = isset($config['name']) ? $config['name'] : $this->name;
            $this->pconnect = isset($config['pconnect']) ? $config['pconnect'] : $this->pconnect;
            $this->open($this->name);
            if ($this->conn && sqlite_last_error($this->conn)==0) {
                $this->ready = true;
            }
        }
    }
    /**
     * 打开数据库
     *
     * @param string $dbname
     * @param int $mode
     * @return null|void
     */
    public function open($dbname, $mode=0666) {
        $error = '';
        // 连接数据库
        if (error_reporting()==0 || $this->is_database($dbname)) {
            if (function_exists('sqlite_popen') && $this->pconnect) {
                $this->conn = sqlite_popen($dbname, $mode, $error);
            } elseif (function_exists('sqlite_open')) {
                $this->conn = sqlite_open($dbname, $mode, $error);
            }
        } else {
            upf_error(__('数据库不存在！'));
        }
        // 验证连接是否正确
        if (!$this->conn) {
            upf_error(sprintf(__('数据库链接错误：%s'), $error));
        }
        // 设置10秒等待
        sqlite_busy_timeout($this->conn, 1500);
        // 设置字段模式
        sqlite_exec($this->conn,"pragma short_column_names=ON;");
        // 注入自定义函数
        $this->apply_plugins();
                
        return $this->conn;
    }
    /**
     * 执行自定义函数
     *
     * @return void
     */
    private function apply_plugins() {
        sqlite_create_function($this->conn, 'ucase', 'strtoupper', 1);
        sqlite_create_function($this->conn, 'from_unixtime', array('DBQuery', 'strftime'), 2);
        sqlite_create_function($this->conn, 'find_in_set', 'instr', 2);
        sqlite_create_function($this->conn, 'list_slice', array('DBQuery', 'list_slice'), 3);
    }
    /**
     * 执行查询
     *
     * @param string $sql
     * @return bool
     */
    public function query($sql){
        // 验证连接是否正确
        if (!$this->conn) {
            upf_error(__('提供的参数不是一个有效的SQLite的链接资源。'));
        }
        $args = func_get_args();

        $sql = call_user_func_array(array(&$this,'prepare'), $args);

        if ( preg_match("/^\\s*(insert|delete|update|replace|alter table|create) /i",$sql) ) {
            $func = 'sqlite_exec';
        } else {
            $func = 'sqlite_query';
        }

        $_begin_time = microtime(true);

        $this->last_sql = $sql;
        // 统计SQL执行次数
        DBQuery::$query_count++;
        // 执行SQL
        if (!($result = $func($this->conn, $sql))) {
            upf_error(sprintf(__('SQLite 查询错误：%s'),$sql."\r\n\t".sqlite_error_string(sqlite_last_error($this->conn))));
        }
        // 查询正常
        else {
            // 返回结果
            if ($func == 'sqlite_exec') {
                if ( preg_match("/^\\s*(insert|replace) /i", $sql) ) {
                    $result = ($insert_id = sqlite_last_insert_rowid($this->conn)) >= 0 ? $insert_id : $this->result("select last_insert_rowid();");
                } else {
                    $result = sqlite_changes($this->conn);
                }
            }
        }
        // 记录sql执行日志
        Logger::instance()->debug(sprintf('%01.6f SQL: %s', microtime(true) - $_begin_time, $sql));
        return $result;
    }
    /**
     * 检查是否存在数据库
     *
     * @param string $dbname
     * @return bool
     */
    public function is_database($dbname) {
        return is_file($dbname);
    }
    /**
     * 判断数据表是否存在
     *
     * 注意表名的大小写，是有区别的
     *
     * @param string $table    table
     * @return bool
     */
    public function is_table($table){
        $res = $this->query("select [name] from [sqlite_master] where [type]='table';");
        if (strncmp($table, '#@_', 3) === 0)
            $table = str_replace('#@_', $this->prefix, $table);

        while ($rs = $this->fetch($res,0)) {
            if ($table == $rs[0]) return true;
        }
        return false;
    }
    /**
     * 列出表里的所有字段
     *
     * @param string $table    表名
     */
    public function list_fields($table){
        static $tables = array();
        if (empty($tables[$table])) {
            $res = $this->query(sprintf("pragma table_info([%s]);", $table));
            while ($row = $this->fetch($res)) {
                $tables[$table][] = ($row['pk'] == 1 ? '*' : '') . $row['name'];
            }
        }
        return $tables[$table];
    }
    /**
     * 获取自增ID
     *
     * @param string $table
     * @return int
     */
    public function autoindex($table) {
        $fields = $this->list_fields($table);
        foreach($fields as $field) {
            if (strncmp($field,'*',1)===0) {
                $field = substr($field, 1);
                break;
            }
        }
        $res = $this->query(sprintf("select (max(`%s`) + 1) as `Auto_increment` from `%s`;", $field, $table));
        if ($data = $this->fetch($res)) {
            return $data['Auto_increment'];
        }
        return 1;
    }
    /**
     * 取得数据集的单条记录
     *
     * @param resource $result
     * @param int $mode
     * @return array
     */
    public function fetch($result,$mode=1){
        switch (intval($mode)) {
            case 0: $mode = SQLITE_NUM;break;
            case 1: $mode = SQLITE_ASSOC;break;
            case 2: $mode = SQLITE_BOTH;break;
        }
        return sqlite_fetch_array($result, $mode);
    }
    /**
     * SQLite 版本
     *
     * @return string
     */
    public function version(){
        return sqlite_libversion();
    }
    /**
     * 关闭链接
     *
     * @return bool
     */
    public function close(){
        if (is_resource($this->conn)) {
            return sqlite_close($this->conn);
        }
        return false;
    }
    /**
     * 转义SQL语句
     *
     * @param mixed $value
     * @return string
     */
    public function escape($value){
        // 空
        if ($value === null) return '';
        // 转义变量
        $value = $this->envalue($value);

        return sqlite_escape_string($value);
    }
}
