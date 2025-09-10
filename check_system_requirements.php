<?php
/**
 * 系统环境检查脚本
 */

echo "🔍 系统环境检查\n";
echo "==============\n\n";

$errors = [];
$warnings = [];

// 1. PHP版本检查
echo "1. PHP环境检查:\n";
$phpVersion = PHP_VERSION;
echo "PHP版本: $phpVersion\n";

if (version_compare($phpVersion, '7.4.0', '<')) {
    $errors[] = "PHP版本过低，需要7.4+，当前版本: $phpVersion";
} else {
    echo "✅ PHP版本符合要求\n";
}

// 2. 必需函数检查
echo "\n2. 必需函数检查:\n";
$requiredFunctions = [
    'exec' => '执行系统命令',
    'shell_exec' => '执行shell命令',
    'file_get_contents' => '读取文件',
    'file_put_contents' => '写入文件',
    'json_encode' => 'JSON编码',
    'json_decode' => 'JSON解码',
    'filter_var' => '数据验证',
    'sys_getloadavg' => '获取系统负载',
    'memory_get_usage' => '获取内存使用',
    'ini_get' => '获取配置信息'
];

foreach ($requiredFunctions as $func => $desc) {
    if (function_exists($func)) {
        echo "✅ $func - $desc\n";
    } else {
        $errors[] = "缺少必需函数: $func ($desc)";
    }
}

// 3. 系统工具检查
echo "\n3. 系统工具检查:\n";
$tools = [
    'wget' => '下载工具',
    'ffmpeg' => '视频处理工具',
    'ffprobe' => '视频信息工具'
];

foreach ($tools as $tool => $desc) {
    $output = [];
    exec("which $tool 2>/dev/null", $output, $returnCode);
    if ($returnCode === 0) {
        echo "✅ $tool - $desc\n";
    } else {
        $errors[] = "缺少系统工具: $tool ($desc)";
    }
}

// 4. 权限检查
echo "\n4. 权限检查:\n";
$tempDir = sys_get_temp_dir();
if (is_writable($tempDir)) {
    echo "✅ 临时目录可写: $tempDir\n";
} else {
    $errors[] = "临时目录不可写: $tempDir";
}

// 5. 内存和CPU检查
echo "\n5. 系统资源检查:\n";
$memoryLimit = ini_get('memory_limit');
$memoryUsage = memory_get_usage(true);
$cpuLoad = sys_getloadavg()[0];

echo "内存限制: $memoryLimit\n";
echo "当前内存使用: " . number_format($memoryUsage / 1024 / 1024, 2) . " MB\n";
echo "CPU负载: $cpuLoad\n";

if ($cpuLoad > 2.0) {
    $warnings[] = "CPU负载较高: $cpuLoad";
}

// 6. 数据库连接检查
echo "\n6. 数据库连接检查:\n";
try {
    require_once 'config/database.php';
    $db = new Database();
    $result = $db->fetchOne("SELECT 1 as test");
    if ($result) {
        echo "✅ 数据库连接正常\n";
    } else {
        $errors[] = "数据库连接失败";
    }
} catch (Exception $e) {
    $errors[] = "数据库连接错误: " . $e->getMessage();
}

// 7. 配置文件检查
echo "\n7. 配置文件检查:\n";
$configFiles = [
    'config/config.php',
    'config/database.php',
    'includes/classes/FastLightweightRecorder.php'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file\n";
    } else {
        $errors[] = "缺少配置文件: $file";
    }
}

// 8. 输出结果
echo "\n" . str_repeat("=", 50) . "\n";
echo "检查结果:\n";

if (empty($errors)) {
    echo "🎉 系统环境检查通过！\n";
    if (!empty($warnings)) {
        echo "\n⚠️ 警告:\n";
        foreach ($warnings as $warning) {
            echo "- $warning\n";
        }
    }
} else {
    echo "❌ 发现以下问题:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
    echo "\n请解决上述问题后重新检查。\n";
}

echo "\n推荐配置:\n";
echo "- PHP版本: 7.4+\n";
echo "- 内存限制: 512M+\n";
echo "- 最大执行时间: 300秒+\n";
echo "- 必需工具: wget, ffmpeg, ffprobe\n";
?>
