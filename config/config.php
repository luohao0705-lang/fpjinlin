<?php
/**
 * 系统配置文件
 * 复盘精灵系统
 */

// 加载环境变量（自动检测服务器环境）
// 优先使用简化版本，避免putenv()被禁用的问题
try {
    if (function_exists('putenv') && !in_array('putenv', explode(',', ini_get('disable_functions')))) {
        require_once __DIR__ . '/env.php';
    } else {
        require_once __DIR__ . '/env_simple.php';
        // 使用简化版本的别名
        class_alias('EnvLoaderSimple', 'EnvLoader');
    }
} catch (Exception $e) {
    // 如果出现任何问题，强制使用简化版本
    require_once __DIR__ . '/env_simple.php';
    class_alias('EnvLoaderSimple', 'EnvLoader');
}

// 错误报告设置
error_reporting(E_ALL);
$debug = EnvLoader::get('APP_DEBUG', 'false') === 'true';
ini_set('display_errors', $debug ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 系统常量
define('APP_NAME', EnvLoader::get('APP_NAME', '复盘精灵'));
define('APP_VERSION', EnvLoader::get('APP_VERSION', '1.0.0'));
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/' . EnvLoader::get('UPLOAD_PATH', 'assets/uploads'));
define('LOG_PATH', BASE_PATH . '/logs');

// 文件上传配置
define('MAX_UPLOAD_SIZE', (int)EnvLoader::get('MAX_UPLOAD_SIZE', 10 * 1024 * 1024)); // 默认10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_SCRIPT_TYPES', ['txt']);

// 精灵币配置
define('DEFAULT_ANALYSIS_COST', 10); // 默认分析消耗精灵币

// 短信配置
define('SMS_CODE_LENGTH', 6);
define('SMS_CODE_EXPIRE', 300); // 5分钟

// API配置
define('DEEPSEEK_API_TIMEOUT', 60); // API超时时间(秒)

// 安全配置
define('PASSWORD_MIN_LENGTH', (int)EnvLoader::get('PASSWORD_MIN_LENGTH', 6));
define('SESSION_LIFETIME', (int)EnvLoader::get('SESSION_LIFETIME', 7200)); // 2小时
define('LOGIN_MAX_ATTEMPTS', (int)EnvLoader::get('LOGIN_MAX_ATTEMPTS', 5)); // 最大登录尝试次数

// 分页配置
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

/**
 * 自动加载函数
 */
function autoload($className) {
    $directories = [
        BASE_PATH . '/includes/classes/',
        BASE_PATH . '/includes/models/',
        BASE_PATH . '/includes/services/',
        BASE_PATH . '/fpjinlin/includes/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
}

spl_autoload_register('autoload');

/**
 * 初始化错误处理器
 */
ErrorHandler::init();

/**
 * 创建必要的目录
 */
function createDirectories() {
    $directories = [
        LOG_PATH,
        UPLOAD_PATH,
        UPLOAD_PATH . '/screenshots',
        UPLOAD_PATH . '/covers',
        UPLOAD_PATH . '/scripts',
        UPLOAD_PATH . '/reports'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

createDirectories();

/**
 * 启动安全会话
 */
SessionManager::start();

/**
 * 通用响应函数
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 错误处理函数
 */
function handleError($message, $code = 500) {
    error_log($message);
    jsonResponse(['success' => false, 'message' => $message], $code);
}

/**
 * 验证手机号格式
 */
function validatePhone($phone) {
    return preg_match('/^1[3-9]\d{9}$/', $phone);
}

/**
 * 生成随机字符串
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * 生成订单号
 */
function generateOrderNo() {
    return 'FP' . date('YmdHis') . rand(1000, 9999);
}

/**
 * 文件大小格式化
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * 安全的文件名
 */
function safeFileName($filename) {
    $info = pathinfo($filename);
    $name = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $info['filename']);
    $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
    return $name . '_' . time() . $ext;
}

/**
 * 获取系统配置（兼容fpjinlin系统）
 */
if (!function_exists('getSystemConfig')) {
    function getSystemConfig($key, $default = null) {
        static $configs = null;
        if ($configs === null) {
            try {
                $db = new Database();
                // 尝试从system_configs表读取
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
                        case 'float':
                            $value = (float)$value;
                            break;
                        case 'json':
                            $value = json_decode($value, true);
                            break;
                        default:
                            // 字符串类型保持原样
                            break;
                    }
                    $configs[$row['config_key']] = $value;
                }
            } catch (Exception $e) {
                // 如果表不存在或查询失败，使用默认配置
                $configs = [
                    'analysis_cost_coins' => 100,
                    'max_competitor_scripts' => 5,
                    'analysis_timeout' => 300,
                    'default_coins_reward' => 50
                ];
            }
        }
        return isset($configs[$key]) ? $configs[$key] : $default;
    }
}