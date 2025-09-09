<?php
// 简单的录制测试
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/VideoProcessor.php';

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>录制测试</h2>";

try {
    // 创建VideoProcessor实例
    $processor = new VideoProcessor();
    
    // 测试FLV地址
    $flvUrl = 'http://pull-flv-l11.douyincdn.com/stage/stream-117938925979566225_or4.flv?arch_hrchy=w1&exp_hrchy=w1&expire=1758043051&major_anchor_level=common&sign=e89d25e0d515043fe75bb9e5f6dc8efc&t_id=037-2025091001173157A1F3440DB6F835A1D0-XfqGZG&unique_id=stream-117938925979566225_145_flv_or4';
    
    echo "<p>测试FLV地址: " . htmlspecialchars($flvUrl) . "</p>";
    
    // 生成测试文件路径
    $outputFile = sys_get_temp_dir() . '/test_recording_' . time() . '.mp4';
    
    echo "<p>输出文件: " . htmlspecialchars($outputFile) . "</p>";
    
    // 检查proc_open是否可用
    if (function_exists('proc_open')) {
        echo "<p>✅ proc_open 可用</p>";
    } else {
        echo "<p>❌ proc_open 不可用，将使用exec</p>";
    }
    
    // 检查exec是否可用
    if (function_exists('exec')) {
        echo "<p>✅ exec 可用</p>";
    } else {
        echo "<p>❌ exec 不可用</p>";
    }
    
    // 检查FFmpeg
    $ffmpegPath = '';
    if (PHP_OS_FAMILY === 'Windows') {
        $ffmpegPath = 'ffmpeg.exe';
    } else {
        $ffmpegPath = 'ffmpeg';
    }
    
    $ffmpegCheck = shell_exec("which {$ffmpegPath} 2>/dev/null");
    if ($ffmpegCheck) {
        echo "<p>✅ FFmpeg 路径: " . trim($ffmpegCheck) . "</p>";
    } else {
        echo "<p>❌ FFmpeg 未找到</p>";
    }
    
    // 测试录制
    echo "<p>开始测试录制...</p>";
    
    $startTime = time();
    $processor->recordVideo(999, $flvUrl); // 使用虚拟ID
    $endTime = time();
    
    echo "<p>✅ 录制完成，耗时: " . ($endTime - $startTime) . " 秒</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>文件: " . $e->getFile() . "</p>";
    echo "<p>行号: " . $e->getLine() . "</p>";
}
?>
