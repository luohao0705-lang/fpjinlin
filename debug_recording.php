<?php
/**
 * 录制调试脚本
 * 帮助诊断录制问题
 */

echo "🔍 录制调试脚本\n";
echo "===============\n\n";

// 1. 检查系统环境
echo "1. 检查系统环境...\n";
echo "PHP版本: " . PHP_VERSION . "\n";
echo "操作系统: " . PHP_OS . "\n";
echo "当前用户: " . get_current_user() . "\n";
echo "临时目录: " . sys_get_temp_dir() . "\n";
echo "内存限制: " . ini_get('memory_limit') . "\n";
echo "执行时间限制: " . ini_get('max_execution_time') . "\n\n";

// 2. 检查FFmpeg
echo "2. 检查FFmpeg...\n";
$ffmpegPath = trim(shell_exec('which ffmpeg 2>/dev/null'));
if ($ffmpegPath) {
    echo "FFmpeg路径: $ffmpegPath\n";
    $version = shell_exec('ffmpeg -version 2>&1 | head -1');
    echo "FFmpeg版本: " . trim($version) . "\n";
} else {
    echo "❌ FFmpeg未安装或不在PATH中\n";
}
echo "\n";

// 3. 检查FFprobe
echo "3. 检查FFprobe...\n";
$ffprobePath = trim(shell_exec('which ffprobe 2>/dev/null'));
if ($ffprobePath) {
    echo "FFprobe路径: $ffprobePath\n";
} else {
    echo "❌ FFprobe未安装或不在PATH中\n";
}
echo "\n";

// 4. 检查目录权限
echo "4. 检查目录权限...\n";
$testDir = '/tmp/recording_test';
if (mkdir($testDir, 0777, true)) {
    echo "✅ 可以创建目录: $testDir\n";
    if (is_writable($testDir)) {
        echo "✅ 目录可写\n";
    } else {
        echo "❌ 目录不可写\n";
    }
    rmdir($testDir);
} else {
    echo "❌ 无法创建目录: $testDir\n";
}
echo "\n";

// 5. 测试网络连接
echo "5. 测试网络连接...\n";
$testUrl = 'https://live.douyin.com/test?expire=' . (time() + 3600);
echo "测试URL: $testUrl\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ 网络错误: $error\n";
} else {
    echo "✅ 网络连接正常，HTTP状态码: $httpCode\n";
    echo "响应长度: " . strlen($response) . " 字节\n";
}
echo "\n";

// 6. 测试FFmpeg命令
echo "6. 测试FFmpeg命令...\n";
$testFile = '/tmp/test_video.mp4';
$command = "ffmpeg -i '$testUrl' -t 5 -c copy '$testFile' -y 2>&1";
echo "执行命令: $command\n";

$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

echo "返回码: $returnCode\n";
echo "输出:\n" . implode("\n", $output) . "\n";

if (file_exists($testFile)) {
    echo "✅ 测试文件创建成功\n";
    echo "文件大小: " . filesize($testFile) . " 字节\n";
    unlink($testFile);
} else {
    echo "❌ 测试文件创建失败\n";
}
echo "\n";

// 7. 检查数据库连接
echo "7. 检查数据库连接...\n";
try {
    require_once 'config/database.php';
    $db = new Database();
    $result = $db->fetchOne("SELECT 1 as test");
    echo "✅ 数据库连接正常\n";
} catch (Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
}
echo "\n";

echo "🔍 调试完成！\n";
?>
