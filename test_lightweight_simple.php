<?php
/**
 * 简单测试轻量级录制器（不依赖数据库）
 */

echo "🧪 测试轻量级录制器\n";
echo "==================\n\n";

// 模拟LightweightVideoRecorder的核心功能
class SimpleLightweightRecorder {
    
    public function checkTool($tool) {
        $output = [];
        exec("which $tool 2>/dev/null", $output, $returnCode);
        return $returnCode === 0;
    }
    
    public function validateFlvUrl($flvUrl) {
        if (!filter_var($flvUrl, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // 检查是否是抖音链接
        if (strpos($flvUrl, 'douyin.com') === false) {
            return false;
        }
        
        // 检查URL是否包含过期时间
        if (strpos($flvUrl, 'expire=') === false) {
            return false;
        }
        
        return true;
    }
    
    public function selectBestMethod($flvUrl) {
        // 检查工具可用性
        $wgetAvailable = $this->checkTool('wget');
        $ytDlpAvailable = $this->checkTool('yt-dlp');
        $ffmpegAvailable = $this->checkTool('ffmpeg');
        
        // 检查系统资源
        $cpuLoad = sys_getloadavg()[0];
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        // 根据资源情况选择方案
        if ($cpuLoad < 0.5 && $wgetAvailable) {
            return 'wget'; // 最轻量
        } elseif ($ytDlpAvailable) {
            return 'yt-dlp'; // 专业工具
        } elseif ($ffmpegAvailable) {
            return 'ffmpeg_copy'; // FFmpeg copy模式
        } else {
            return 'segments'; // 分段下载
        }
    }
    
    private function parseMemoryLimit($memoryLimit) {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit)-1]);
        $memoryLimit = (int) $memoryLimit;
        
        switch($last) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }
        
        return $memoryLimit;
    }
}

try {
    $recorder = new SimpleLightweightRecorder();
    
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
    $testFlvUrl = "https://live.douyin.com/test?expire=1234567890";
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
    
    echo "\n下一步:\n";
    echo "1. 在服务器上执行: chmod +x install_lightweight_tools.sh && ./install_lightweight_tools.sh\n";
    echo "2. 测试录制: php test_lightweight_recording.php\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
}
?>
