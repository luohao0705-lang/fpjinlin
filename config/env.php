<?php
/**
 * 环境变量加载器
 * 复盘精灵系统
 */

class EnvLoader {
    /**
     * 加载.env文件
     */
    public static function load($path = null) {
        $envFile = $path ?: dirname(__DIR__) . '/.env';
        
        if (!file_exists($envFile)) {
            return false;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // 跳过注释行
            if (strpos(trim($line), '#') === 0) {
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
                
                // 设置环境变量（只使用$_ENV，避免putenv被禁用的问题）
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                }
            }
        }
        
        return true;
    }
    
    /**
     * 获取环境变量值
     */
    public static function get($key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
}

// 自动加载环境变量
EnvLoader::load();