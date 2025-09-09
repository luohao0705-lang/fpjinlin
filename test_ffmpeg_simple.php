<?php
/**
 * 简单测试FFmpeg是否正常工作
 */

echo "🧪 测试FFmpeg环境\n";
echo "==================\n\n";

// 1. 检查FFmpeg是否安装
echo "1. 检查FFmpeg安装:\n";
$output = [];
exec('ffmpeg -version 2>&1', $output);
if (!empty($output)) {
    echo "✅ FFmpeg已安装\n";
    echo "版本信息: " . $output[0] . "\n\n";
} else {
    echo "❌ FFmpeg未安装或不可用\n\n";
    exit(1);
}

// 2. 检查FFmpeg基本功能
echo "2. 测试FFmpeg基本功能:\n";
$testFile = sys_get_temp_dir() . '/test_ffmpeg.mp4';
$command = "ffmpeg -f lavfi -i color=red:size=320x240:duration=1 -c:v libx264 -y " . escapeshellarg($testFile) . " 2>&1";
exec($command, $output, $returnCode);

if ($returnCode === 0 && file_exists($testFile)) {
    echo "✅ FFmpeg基本功能正常\n";
    echo "测试文件大小: " . filesize($testFile) . " bytes\n";
    unlink($testFile);
} else {
    echo "❌ FFmpeg基本功能异常\n";
    echo "返回码: $returnCode\n";
    echo "输出: " . implode("\n", $output) . "\n";
}

echo "\n3. 测试网络连接:\n";
$testUrl = "https://www.baidu.com";
$headers = @get_headers($testUrl);
if ($headers && strpos($headers[0], '200') !== false) {
    echo "✅ 网络连接正常\n";
} else {
    echo "❌ 网络连接异常\n";
}

echo "\n4. 检查系统资源:\n";
echo "内存使用: " . memory_get_usage(true) . " bytes\n";
echo "内存限制: " . ini_get('memory_limit') . "\n";

$loadAvg = sys_getloadavg();
echo "CPU负载: " . implode(', ', $loadAvg) . "\n";

echo "\n🎉 测试完成！\n";
?>