<?php
/**
 * 服务器诊断脚本 - 排查502错误
 */

echo "=== 复盘精灵系统诊断报告 ===\n";
echo "时间: " . date('Y-m-d H:i:s') . "\n\n";

// 1. PHP基础信息
echo "1. PHP环境检查\n";
echo "PHP版本: " . PHP_VERSION . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n";
echo "内存限制: " . ini_get('memory_limit') . "\n";
echo "执行时间限制: " . ini_get('max_execution_time') . "秒\n";
echo "错误报告级别: " . error_reporting() . "\n\n";

// 2. 文件权限检查
echo "2. 文件权限检查\n";
$checkPaths = [
    '.',
    'config',
    'logs',
    'assets/uploads',
    'includes'
];

foreach ($checkPaths as $path) {
    if (file_exists($path)) {
        $perms = fileperms($path);
        $readable = is_readable($path) ? '✓' : '✗';
        $writable = is_writable($path) ? '✓' : '✗';
        echo sprintf("%-20s 权限:%o 可读:%s 可写:%s\n", $path, $perms & 0777, $readable, $writable);
    } else {
        echo sprintf("%-20s 不存在\n", $path);
    }
}
echo "\n";

// 3. 配置文件检查
echo "3. 配置文件检查\n";
$configFiles = [
    'config/config.php',
    'config/database.php',
    'config/env_simple.php'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        echo "✓ $file 存在\n";
    } else {
        echo "✗ $file 缺失\n";
    }
}
echo "\n";

// 4. 数据库连接测试
echo "4. 数据库连接测试\n";
try {
    require_once 'config/config.php';
    require_once 'config/database.php';
    
    $db = new Database();
    $connection = $db->getConnection();
    echo "✓ 数据库连接成功\n";
    
    // 测试关键表
    $tables = ['users', 'analysis_orders', 'exchange_codes', 'coin_transactions'];
    foreach ($tables as $table) {
        try {
            $result = $db->fetchOne("SELECT COUNT(*) as count FROM $table");
            echo "✓ 表 $table 存在，记录数: " . $result['count'] . "\n";
        } catch (Exception $e) {
            echo "✗ 表 $table 错误: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. 类文件加载测试
echo "5. 关键类文件测试\n";
$classes = [
    'User' => 'includes/classes/User.php',
    'Database' => 'config/database.php',
    'AnalysisOrder' => 'includes/classes/AnalysisOrder.php',
    'ExchangeCode' => 'includes/classes/ExchangeCode.php'
];

foreach ($classes as $className => $filePath) {
    if (file_exists($filePath)) {
        try {
            if (!class_exists($className)) {
                require_once $filePath;
            }
            if (class_exists($className)) {
                echo "✓ 类 $className 加载成功\n";
            } else {
                echo "✗ 类 $className 加载失败\n";
            }
        } catch (Exception $e) {
            echo "✗ 类 $className 错误: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✗ 文件 $filePath 不存在\n";
    }
}
echo "\n";

// 6. Session测试
echo "6. Session功能测试\n";
try {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    echo "✓ Session启动成功\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Session存储路径: " . session_save_path() . "\n";
} catch (Exception $e) {
    echo "✗ Session错误: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. 错误日志检查
echo "7. 错误日志检查\n";
$logFile = 'logs/error.log';
if (file_exists($logFile)) {
    $logSize = filesize($logFile);
    echo "✓ 错误日志存在，大小: " . formatBytes($logSize) . "\n";
    
    if ($logSize > 0) {
        echo "最近的错误日志:\n";
        $lines = file($logFile);
        $recentLines = array_slice($lines, -10);
        foreach ($recentLines as $line) {
            echo "  " . trim($line) . "\n";
        }
    }
} else {
    echo "✗ 错误日志文件不存在\n";
}
echo "\n";

// 8. 内存和性能检查
echo "8. 系统资源检查\n";
echo "当前内存使用: " . formatBytes(memory_get_usage()) . "\n";
echo "峰值内存使用: " . formatBytes(memory_get_peak_usage()) . "\n";
echo "磁盘可用空间: " . formatBytes(disk_free_space('.')) . "\n";
echo "\n";

// 9. 常见502错误原因检查
echo "9. 502错误可能原因分析\n";
$issues = [];

// 检查PHP-FPM相关
if (function_exists('php_uname')) {
    echo "系统信息: " . php_uname() . "\n";
}

// 检查超时设置
$timeout = ini_get('max_execution_time');
if ($timeout < 30) {
    $issues[] = "执行时间限制过短 ({$timeout}秒)";
}

// 检查内存限制
$memLimit = ini_get('memory_limit');
if (preg_match('/(\d+)([MG])/', $memLimit, $matches)) {
    $bytes = $matches[1] * ($matches[2] == 'G' ? 1024*1024*1024 : 1024*1024);
    if ($bytes < 128*1024*1024) {
        $issues[] = "内存限制过低 ($memLimit)";
    }
}

// 检查关键扩展
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $issues[] = "缺少PHP扩展: $ext";
    }
}

if (empty($issues)) {
    echo "✓ 未发现明显的配置问题\n";
} else {
    echo "发现的潜在问题:\n";
    foreach ($issues as $issue) {
        echo "  ⚠ $issue\n";
    }
}
echo "\n";

echo "=== 诊断完成 ===\n";
echo "如果问题仍然存在，建议检查:\n";
echo "1. Nginx/Apache错误日志\n";
echo "2. PHP-FPM错误日志\n";
echo "3. 系统资源使用情况\n";
echo "4. 数据库连接池状态\n";

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>