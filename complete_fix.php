<?php
/**
 * 完整修复脚本
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔧 完整修复脚本\n";
echo "==============\n\n";

// 1. 检查PHP配置
echo "1. 检查PHP配置:\n";
$memoryLimit = ini_get('memory_limit');
$maxExecutionTime = ini_get('max_execution_time');

echo "当前内存限制: $memoryLimit\n";
echo "当前最大执行时间: $maxExecutionTime\n";

if (intval($memoryLimit) < 512) {
    echo "⚠️ 内存限制过低，建议设置为512M+\n";
} else {
    echo "✅ 内存限制符合要求\n";
}

if ($maxExecutionTime < 300 && $maxExecutionTime != 0) {
    echo "⚠️ 最大执行时间过短，建议设置为300秒+\n";
} else {
    echo "✅ 最大执行时间符合要求\n";
}

// 2. 检查系统工具
echo "\n2. 检查系统工具:\n";
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
echo "\n3. 检查文件权限:\n";
$tempDir = sys_get_temp_dir();
if (is_writable($tempDir)) {
    echo "✅ 临时目录可写: $tempDir\n";
} else {
    echo "❌ 临时目录不可写: $tempDir\n";
}

// 4. 检查数据库连接
echo "\n4. 检查数据库连接:\n";
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

// 5. 检查核心文件
echo "\n5. 检查核心文件:\n";
$coreFiles = [
    'includes/classes/FastLightweightRecorder.php',
    'includes/classes/VideoProcessor.php',
    'includes/classes/VideoAnalysisOrder.php',
    'config/config.php',
    'config/database.php'
];

foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file\n";
    } else {
        echo "❌ 缺少文件: $file\n";
    }
}

// 6. 清理失败任务
echo "\n6. 清理失败任务:\n";
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $db = new Database();
        
        // 重置失败的任务
        $result1 = $db->query(
            "UPDATE video_processing_queue 
             SET status = 'pending', error_message = NULL, retry_count = 0 
             WHERE status = 'failed'"
        );
        echo "✅ 重置失败任务: " . $result1 . " 条\n";
        
        // 重置处理中的任务
        $result2 = $db->query(
            "UPDATE video_processing_queue 
             SET status = 'pending', error_message = NULL, retry_count = 0 
             WHERE status = 'processing'"
        );
        echo "✅ 重置处理中任务: " . $result2 . " 条\n";
        
        // 重置视频文件状态
        $result3 = $db->query(
            "UPDATE video_files 
             SET status = 'pending', recording_progress = 0, recording_status = 'pending' 
             WHERE status IN ('failed', 'recording')"
        );
        echo "✅ 重置视频文件状态: " . $result3 . " 条\n";
        
    } else {
        echo "❌ 无法清理任务，缺少数据库配置\n";
    }
} catch (Exception $e) {
    echo "❌ 清理任务失败: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "修复完成！\n";
echo "\n下一步操作:\n";
echo "1. 如果PHP配置有问题，请运行: chmod +x fix_php_config.sh && ./fix_php_config.sh\n";
echo "2. 重启Web服务器: chmod +x restart_web_server.sh && ./restart_web_server.sh\n";
echo "3. 测试快速录制器: php test_fast_recording.php\n";
echo "4. 在后台点击'启动分析'测试录制\n";
?>