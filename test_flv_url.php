<?php
/**
 * 测试FLV地址是否有效
 */

require_once 'config/config.php';
require_once 'config/database.php';

echo "🧪 测试FLV地址有效性\n";
echo "==================\n\n";

try {
    $db = new Database();
    
    // 获取一个待处理的视频文件
    $videoFile = $db->fetchOne(
        "SELECT * FROM video_files WHERE status = 'pending' AND flv_url IS NOT NULL AND flv_url != '' LIMIT 1"
    );
    
    if (!$videoFile) {
        echo "❌ 没有找到待处理的视频文件\n";
        exit(1);
    }
    
    echo "找到视频文件: ID {$videoFile['id']}\n";
    echo "FLV地址: {$videoFile['flv_url']}\n\n";
    
    // 测试FLV地址
    $flvUrl = $videoFile['flv_url'];
    
    echo "1. 检查URL格式:\n";
    if (filter_var($flvUrl, FILTER_VALIDATE_URL)) {
        echo "✅ URL格式正确\n";
    } else {
        echo "❌ URL格式错误\n";
        exit(1);
    }
    
    echo "\n2. 检查URL可访问性:\n";
    $headers = @get_headers($flvUrl, 1);
    if ($headers && strpos($headers[0], '200') !== false) {
        echo "✅ URL可访问\n";
    } else {
        echo "❌ URL不可访问\n";
        echo "响应: " . ($headers[0] ?? '无响应') . "\n";
    }
    
    echo "\n3. 测试FFmpeg连接:\n";
    $testFile = sys_get_temp_dir() . '/test_flv_' . time() . '.mp4';
    $command = "ffmpeg -user_agent 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36' -headers 'Referer: https://live.douyin.com/' -i " . escapeshellarg($flvUrl) . " -t 5 -c:v libx264 -preset ultrafast -y " . escapeshellarg($testFile) . " 2>&1";
    
    echo "执行命令: $command\n\n";
    
    $output = [];
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($testFile)) {
        echo "✅ FFmpeg录制成功\n";
        echo "文件大小: " . filesize($testFile) . " bytes\n";
        unlink($testFile);
    } else {
        echo "❌ FFmpeg录制失败\n";
        echo "返回码: $returnCode\n";
        echo "错误信息:\n" . implode("\n", $output) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
}
?>
