<?php
/**
 * 简化的环境变量加载器（适用于受限服务器环境）
 * 复盘精灵系统
 */

class EnvLoaderSimple {
    private static $loaded = false;
    private static $envVars = [];
    
    /**
     * 加载.env文件
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return true;
        }
        
        $envFile = $path ?: dirname(__DIR__) . '/.env';
        
        if (!file_exists($envFile)) {
            // 如果.env文件不存在，使用默认配置
            self::setDefaults();
            self::$loaded = true;
            return false;
        }
        
        $content = file_get_contents($envFile);
        if ($content === false) {
            self::setDefaults();
            self::$loaded = true;
            return false;
        }
        
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // 跳过空行和注释行
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // 解析键值对
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // 移除引号
                if (preg_match('/^["\'](.*)["\']\s*$/', $value, $matches)) {
                    $value = $matches[1];
                }
                
                // 存储到内部数组和$_ENV
                self::$envVars[$key] = $value;
                $_ENV[$key] = $value;
            }
        }
        
        self::$loaded = true;
        return true;
    }
    
    /**
     * 设置默认配置
     */
    private static function setDefaults() {
        $defaults = [
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_NAME' => 'fupan_jingling',
            'DB_USER' => 'webapp',
            'DB_PASS' => 'password123',
            'ADMIN_DEFAULT_PASSWORD' => 'admin123',
            'SESSION_LIFETIME' => '7200',
            'LOGIN_MAX_ATTEMPTS' => '5',
            'PASSWORD_MIN_LENGTH' => '6',
            'MAX_UPLOAD_SIZE' => '10485760',
            'UPLOAD_PATH' => 'assets/uploads',
            'APP_NAME' => '复盘精灵',
            'APP_VERSION' => '1.0.0',
            'APP_DEBUG' => 'false',
            'DEEPSEEK_API_URL' => 'https://api.deepseek.com/v1/chat/completions'
        ];
        
        foreach ($defaults as $key => $value) {
            self::$envVars[$key] = $value;
            $_ENV[$key] = $value;
        }
    }
    
    /**
     * 获取环境变量值
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$envVars[$key] ?? $_ENV[$key] ?? $default;
    }
    
    /**
     * 设置环境变量
     */
    public static function set($key, $value) {
        self::$envVars[$key] = $value;
        $_ENV[$key] = $value;
    }
    
    /**
     * 检查是否已加载
     */
    public static function isLoaded() {
        return self::$loaded;
    }
}

// 自动加载环境变量
EnvLoaderSimple::load();