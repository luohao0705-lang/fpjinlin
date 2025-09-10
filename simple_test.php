<?php
/**
 * 简单测试脚本
 */

echo "🧪 简单测试脚本\n";
echo "==============\n\n";

// 1. 检查PHP配置
echo "1. PHP配置检查:\n";
echo "内存限制: " . ini_get('memory_limit') . "\n";
echo "最大执行时间: " . ini_get('max_execution_time') . "\n";
echo "最大输入时间: " . ini_get('max_input_time') . "\n";

// 2. 检查系统工具
echo "\n2. 系统工具检查:\n";
$tools = ['wget', 'ffmpeg', 'ffprobe'];
foreach ($tools as $tool) {
    $output = [];
    exec("which $tool 2>/dev/null", $output, $returnCode);
    if ($returnCode === 0) {
        echo "✅ $tool 可用\n";
    } else {
        echo "❌ $tool 不可用\n";
    }
}

// 3. 检查文件权限
echo "\n3. 文件权限检查:\n";
$tempDir = sys_get_temp_dir();
if (is_writable($tempDir)) {
    echo "✅ 临时目录可写: $tempDir\n";
} else {
    echo "❌ 临时目录不可写: $tempDir\n";
}

// 4. 检查核心文件
echo "\n4. 核心文件检查:\n";
$coreFiles = [
    'includes/classes/FastLightweightRecorder.php',
    'includes/classes/VideoProcessor.php',
    'includes/classes/VideoAnalysisOrder.php'
];

foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file\n";
    } else {
        echo "❌ 缺少文件: $file\n";
    }
}

// 5. 测试数据库连接
echo "\n5. 数据库连接测试:\n";
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $db = new Database();
        $result = $db->fetchOne("SELECT 1 as test");
        if ($result) {
            echo "✅ 数据库连接正常\n";
        } else {
            echo "❌ 数据库连接失败\n";
        }
    } else {
        echo "❌ 缺少数据库配置文件\n";
    }
} catch (Exception $e) {
    echo "❌ 数据库连接错误: " . $e->getMessage() . "\n";
}

echo "\n🎉 测试完成！\n";
?>
