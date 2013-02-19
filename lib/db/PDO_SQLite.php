<?php
/**
 * pdo_sqlite 访问类
 *
 * @author  Lukin <my@lukin.cn>
 * @version $Id$
 */
class DB_PDO_SQLite extends DBQuery {

    /**
     * 初始化链接
     *
     * @param array $config
     */
    public function __construct($config) {
        if (!extension_loaded('pdo_sqlite')) {
            upf_error(sprintf(__('您的 PHP 似乎缺少所需的 %s 扩展。'), 'PDO_SQLite'));
        }
        if (!empty($config)) {
            $this->name = isset($config['name']) ? $config['name'] : $this->name;
            $this->scheme = isset($config['scheme']) ? substr($config['scheme'], 4) : $this->scheme;
            $this->open($this->name);
            if ($this->conn && $this->errno()==0) {
                $this->ready = true;
            }
        }
    }

    /**
     * 打开数据库
     *
     * @param string $dbname
     * @return null|void
     */
    public function open($dbname) {
        if (error_reporting()==0 || $this->is_database($dbname)) {
            $this->conn = new PDO(sprintf('%s:%s', $this->scheme, $dbname), null, null);
        } else {
            upf_error(__('数据库不存在！'));
        }
        // 验证连接是否正确
        if ($this->errno() != 0) {
            upf_error(sprintf(__('数据库链接错误：%s'), $this->error()));
        }
        // 设置字段模式
        $this->conn->exec("pragma short_column_names=ON;");
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
        $this->conn->sqliteCreateFunction('ucase', 'strtoupper', 1);
        $this->conn->sqliteCreateFunction('from_unixtime', array('DBQuery', 'strftime'), 2);
        $this->conn->sqliteCreateFunction('find_in_set', 'instr', 2);
        $this->conn->sqliteCreateFunction('list_slice', array('DBQuery', 'list_slice'), 3);
    }
    /**
     * 执行查询
     *
     * @param string $sql
     * @return bool
     */
    public function query($sql){
        // 验证连接是否正确
        if (!is_object($this->conn)) {
            upf_error(__('提供的参数不是一个有效的SQLite对象。'));
        }
        $args = func_get_args();

        $sql = call_user_func_array(array(&$this,'prepare'), $args);

        if ( preg_match("/^\\s*(insert|delete|update|replace|alter table|create) /i",$sql) ) {
            $func = 'exec';
        } else {
            $func = 'query';
        }

        $_begin_time = microtime(true);

        $this->last_sql = $sql;
        // 统计SQL执行次数
        DBQuery::$query_count++;
        // 执行SQL
        $result = $this->conn->$func($sql);
        if ($this->errno() != 0) {
            upf_error(sprintf(__('SQLite 查询错误：%s'),$sql."\r\n\t".$this->error()));
        }
        // 查询正常
        else {
            // 返回结果
            if ($func == 'exec') {
                if ( preg_match("/^\\s*(insert|replace) /i", $sql) ) {
                    $result = ($insert_id = $this->conn->lastInsertId()) >= 0 ? $insert_id : $this->result("select last_insert_rowid();");
                } else {
                    $result = $this->result("select changes();");
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
     * @param PDOStatement $result
     * @param int $mode
     * @return array
     */
    public function fetch($result, $mode=1){
        switch (intval($mode)) {
            case 0: $mode = PDO::FETCH_NUM;break;
            case 1: $mode = PDO::FETCH_ASSOC;break;
            case 2: $mode = PDO::FETCH_BOTH;break;
        }
        return $result->fetch($mode);
    }

    /**
     * SQLite 版本
     *
     * @return string
     */
    public function version(){
        return $this->conn->getAttribute(PDO::ATTR_CLIENT_VERSION);
    }
    /**
     * 错误码
     *
     * @return int
     */
    private function errno() {
        $errno = $this->conn->errorCode();
        return empty($errno) ||$errno=='00000' ? 0 : $errno;
    }
    /**
     * 错误信息
     *
     * @return string
     */
    private function error() {
        $error = $this->conn->errorInfo();
        return $error[2];
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

        return str_replace("'", "''", $value);
    }
    /**
     * 关闭链接
     *
     * @return bool
     */
    public function close(){
        return true;
    }
}
