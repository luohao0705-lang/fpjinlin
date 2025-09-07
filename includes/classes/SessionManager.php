<?php
/**
 * 会话管理类
 * 复盘精灵系统
 */

class SessionManager {
    
    /**
     * 启动安全会话
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // 设置会话安全选项（使用@避免在受限环境下的警告）
            @ini_set('session.cookie_httponly', 1);
            @ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            @ini_set('session.use_strict_mode', 1);
            @ini_set('session.gc_maxlifetime', defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200);
            
            session_start();
            
            // 检查会话是否过期
            self::checkExpiration();
            
            // 检查会话劫持
            self::checkHijacking();
        }
    }
    
    /**
     * 安全登录
     */
    public static function login($userId, $userType = 'user', $additionalData = []) {
        // 重新生成会话ID防止会话固定攻击
        session_regenerate_id(true);
        
        // 设置会话数据
        $_SESSION["{$userType}_id"] = $userId;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['ip_address'] = self::getClientIP();
        
        // 设置额外数据
        foreach ($additionalData as $key => $value) {
            $_SESSION[$key] = $value;
        }
        
        // 记录登录日志
        error_log("User {$userType}:{$userId} logged in from " . $_SESSION['ip_address']);
    }
    
    /**
     * 安全退出
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // 清空会话数据
            $_SESSION = [];
            
            // 删除会话cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // 销毁会话
            session_destroy();
        }
    }
    
    /**
     * 检查会话是否过期
     */
    private static function checkExpiration() {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                self::logout();
                throw new Exception('会话已过期，请重新登录');
            }
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * 检查会话劫持
     */
    private static function checkHijacking() {
        // 检查User Agent
        if (isset($_SESSION['user_agent'])) {
            if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
                self::logout();
                throw new Exception('会话安全验证失败，请重新登录');
            }
        }
        
        // 检查IP地址（可选，因为用户可能会换网络）
        if (isset($_SESSION['ip_address']) && defined('STRICT_IP_CHECK') && STRICT_IP_CHECK) {
            if ($_SESSION['ip_address'] !== self::getClientIP()) {
                self::logout();
                throw new Exception('会话安全验证失败，请重新登录');
            }
        }
    }
    
    /**
     * 检查用户是否已登录
     */
    public static function isLoggedIn($userType = 'user') {
        return isset($_SESSION["{$userType}_id"]) && !empty($_SESSION["{$userType}_id"]);
    }
    
    /**
     * 获取当前用户ID
     */
    public static function getUserId($userType = 'user') {
        return $_SESSION["{$userType}_id"] ?? null;
    }
    
    /**
     * 获取客户端IP
     */
    private static function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * 设置CSRF令牌
     */
    public static function setCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * 验证CSRF令牌
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}