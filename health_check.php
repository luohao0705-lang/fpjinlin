<?php
/**
 * 简单的健康检查页面
 */

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$status = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'memory_usage' => memory_get_usage(true),
    'checks' => []
];

// 1. 基础PHP检查
$status['checks']['php'] = [
    'status' => 'ok',
    'version' => PHP_VERSION,
    'sapi' => php_sapi_name()
];

// 2. 配置文件检查
try {
    if (file_exists('config/config.php')) {
        require_once 'config/config.php';
        $status['checks']['config'] = ['status' => 'ok', 'message' => '配置文件加载成功'];
    } else {
        throw new Exception('配置文件不存在');
    }
} catch (Exception $e) {
    $status['checks']['config'] = ['status' => 'error', 'message' => $e->getMessage()];
    $status['status'] = 'error';
}

// 3. 数据库连接检查
try {
    require_once 'config/database.php';
    $db = new Database();
    $connection = $db->getConnection();
    $status['checks']['database'] = ['status' => 'ok', 'message' => '数据库连接正常'];
} catch (Exception $e) {
    $status['checks']['database'] = ['status' => 'error', 'message' => $e->getMessage()];
    $status['status'] = 'error';
}

// 4. Session检查
try {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $status['checks']['session'] = ['status' => 'ok', 'message' => 'Session正常'];
} catch (Exception $e) {
    $status['checks']['session'] = ['status' => 'error', 'message' => $e->getMessage()];
    $status['status'] = 'error';
}

// 5. 文件权限检查
$writableCheck = is_writable('logs') && is_writable('.');
$status['checks']['permissions'] = [
    'status' => $writableCheck ? 'ok' : 'warning',
    'message' => $writableCheck ? '文件权限正常' : '部分目录不可写'
];

// 输出结果
echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>