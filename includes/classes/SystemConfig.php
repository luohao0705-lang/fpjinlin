<?php
/**
 * 系统配置管理类
 * 负责管理系统配置参数
 */

class SystemConfig {
    private $db;
    private $configs = [];
    private $loaded = false;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取配置值
     */
    public function get($key, $default = null) {
        if (!$this->loaded) {
            $this->loadConfigs();
        }
        
        return isset($this->configs[$key]) ? $this->configs[$key] : $default;
    }
    
    /**
     * 设置配置值
     */
    public function set($key, $value) {
        if (!$this->loaded) {
            $this->loadConfigs();
        }
        
        $this->configs[$key] = $value;
        
        // 保存到数据库
        $this->saveConfig($key, $value);
    }
    
    /**
     * 加载所有配置
     */
    private function loadConfigs() {
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->query("SELECT config_key, config_value FROM system_configs");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $this->configs[$row['config_key']] = $row['config_value'];
            }
            
            $this->loaded = true;
        } catch (Exception $e) {
            error_log("加载系统配置失败: " . $e->getMessage());
            $this->loaded = true; // 避免重复尝试
        }
    }
    
    /**
     * 保存配置到数据库
     */
    private function saveConfig($key, $value) {
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO system_configs (config_key, config_value, config_type, updated_at) 
                VALUES (?, ?, 'string', NOW())
                ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value),
                updated_at = NOW()
            ");
            $stmt->execute([$key, $value]);
        } catch (Exception $e) {
            error_log("保存系统配置失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取所有配置
     */
    public function getAll() {
        if (!$this->loaded) {
            $this->loadConfigs();
        }
        
        return $this->configs;
    }
    
    /**
     * 检查配置是否存在
     */
    public function has($key) {
        if (!$this->loaded) {
            $this->loadConfigs();
        }
        
        return isset($this->configs[$key]);
    }
    
    /**
     * 删除配置
     */
    public function remove($key) {
        if (!$this->loaded) {
            $this->loadConfigs();
        }
        
        unset($this->configs[$key]);
        
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("DELETE FROM system_configs WHERE config_key = ?");
            $stmt->execute([$key]);
        } catch (Exception $e) {
            error_log("删除系统配置失败: " . $e->getMessage());
        }
    }
    
    /**
     * 重新加载配置
     */
    public function reload() {
        $this->loaded = false;
        $this->configs = [];
        $this->loadConfigs();
    }
}
?>
