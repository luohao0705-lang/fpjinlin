<?php
/**
 * 数据库配置文件
 * 复盘精灵系统
 */

// 加载环境变量
require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $database;
    private $username;
    private $password;
    private $port;
    private $charset = 'utf8mb4';
    private $connection = null;
    
    public function __construct() {
        // 从环境变量读取配置
        $this->host = EnvLoader::get('DB_HOST', '127.0.0.1');
        $this->database = EnvLoader::get('DB_NAME', 'fupan_jingling');
        $this->username = EnvLoader::get('DB_USER', 'webapp');
        $this->password = EnvLoader::get('DB_PASS', 'password123');
        $this->port = EnvLoader::get('DB_PORT', '3306');
    }
    
    /**
     * 获取数据库连接
     */
    public function getConnection() {
        if ($this->connection === null) {
            try {
                // 使用TCP连接而不是socket连接
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset={$this->charset}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
                    PDO::ATTR_TIMEOUT => 30
                ];
                
                $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                error_log("数据库连接失败: " . $e->getMessage());
                throw new Exception("数据库连接失败");
            }
        }
        
        return $this->connection;
    }
    
    /**
     * 执行查询
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("SQL执行失败: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("数据库操作失败");
        }
    }
    
    /**
     * 获取单条记录
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 获取多条记录
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * 插入记录并返回ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->getConnection()->lastInsertId();
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        return $this->getConnection()->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollback() {
        return $this->getConnection()->rollback();
    }
}