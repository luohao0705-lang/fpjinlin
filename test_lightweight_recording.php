<?php
/**
 * 测试轻量级录制器
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/LightweightVideoRecorder.php';

echo "🧪 测试轻量级录制器\n";
echo "==================\n\n";

try {
    $recorder = new LightweightVideoRecorder();
    
    // 测试FLV地址（需要替换为真实地址）
    $testFlvUrl = "https://example.com/test.flv"; // 替换为真实的FLV地址
    
    echo "1. 检查系统环境:\n";
    
    // 检查wget
    $wgetAvailable = $recorder->checkTool('wget');
    echo "wget: " . ($wgetAvailable ? "✅ 可用" : "❌ 不可用") . "\n";
    
    // 检查yt-dlp
    $ytDlpAvailable = $recorder->checkTool('yt-dlp');
    echo "yt-dlp: " . ($ytDlpAvailable ? "✅ 可用" : "❌ 不可用") . "\n";
    
    // 检查ffmpeg
    $ffmpegAvailable = $recorder->checkTool('ffmpeg');
    echo "ffmpeg: " . ($ffmpegAvailable ? "✅ 可用" : "❌ 不可用") . "\n";
    
    echo "\n2. 检查系统资源:\n";
    $cpuLoad = sys_getloadavg()[0];
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    
    echo "CPU负载: $cpuLoad\n";
    echo "内存使用: " . number_format($memoryUsage / 1024 / 1024, 2) . " MB\n";
    echo "内存限制: $memoryLimit\n";
    
    echo "\n3. 测试URL验证:\n";
    $validUrl = $recorder->validateFlvUrl($testFlvUrl);
    echo "URL验证: " . ($validUrl ? "✅ 有效" : "❌ 无效") . "\n";
    
    echo "\n4. 选择最佳方案:\n";
    $method = $recorder->selectBestMethod($testFlvUrl);
    echo "推荐方案: $method\n";
    
    echo "\n🎉 测试完成！\n";
    echo "\n建议:\n";
    echo "- 如果wget可用，使用wget方案（最轻量）\n";
    echo "- 如果yt-dlp可用，使用yt-dlp方案（最专业）\n";
    echo "- 如果只有ffmpeg，使用copy模式（最兼容）\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
}
?>
