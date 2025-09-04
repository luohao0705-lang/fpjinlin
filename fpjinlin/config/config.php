<?php
/**
 * 系统配置文件
 * 复盘精灵系统
 */

// 开启错误报告（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 会话配置
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// 网站基础配置
define('SITE_URL', 'http://localhost/fpjinlin');
define('SITE_NAME', '复盘精灵');
define('SITE_VERSION', '1.0.0');

// 文件上传配置
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');

// 允许的文件类型
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_FILE_TYPES', ['txt', 'doc', 'docx', 'pdf']);

// API配置
define('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions');
define('DEEPSEEK_MODEL', 'deepseek-chat');

// 阿里云SMS配置
define('ALIYUN_SMS_REGION', 'cn-hangzhou');
define('ALIYUN_SMS_ENDPOINT', 'dysmsapi.aliyuncs.com');

// 分页配置
define('PAGE_SIZE', 20);
define('ADMIN_PAGE_SIZE', 50);

// 安全配置
define('PASSWORD_MIN_LENGTH', 6);
define('SMS_CODE_LENGTH', 6);
define('SMS_CODE_EXPIRE_MINUTES', 5);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 30);

// JWT配置（如果需要API认证）
define('JWT_SECRET', 'fpjinlin_jwt_secret_key_2024');
define('JWT_EXPIRE_HOURS', 24);

// 缓存配置
define('CACHE_EXPIRE_SECONDS', 3600);

// 系统状态
define('SYSTEM_MAINTENANCE', false);

// 自动加载数据库连接
require_once __DIR__ . '/database.php';

// 工具函数
function getSystemConfig($key, $default = null) {
    static $configs = null;
    if ($configs === null) {
        $db = Database::getInstance();
        $result = $db->fetchAll("SELECT config_key, config_value, config_type FROM system_configs");
        $configs = [];
        foreach ($result as $row) {
            $value = $row['config_value'];
            // 根据类型转换值
            switch ($row['config_type']) {
                case 'int':
                    $value = (int)$value;
                    break;
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            $configs[$row['config_key']] = $value;
        }
    }
    return isset($configs[$key]) ? $configs[$key] : $default;
}

// 生成唯一订单号
function generateOrderNo() {
    return 'FP' . date('YmdHis') . sprintf('%04d', mt_rand(1000, 9999));
}

// 生成兑换码
function generateExchangeCode($length = 12) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $code;
}

// 格式化文件大小
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// 安全函数
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function isValidPhone($phone) {
    return preg_match('/^1[3-9]\d{9}$/', $phone);
}

function generateSMSCode($length = 6) {
    return sprintf('%0' . $length . 'd', mt_rand(0, pow(10, $length) - 1));
}

// 响应函数
function jsonResponse($data, $message = 'success', $code = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse($message, $code = 400, $data = null) {
    jsonResponse($data, $message, $code);
}

function successResponse($data = null, $message = '操作成功') {
    jsonResponse($data, $message, 200);
}
?>