<?php
/**
 * 测试真实抖音FLV录制
 */

require_once 'config/database.php';
require_once 'SimpleRecorder.php';

echo "🎬 真实抖音FLV录制测试\n";
echo "======================\n\n";

try {
    $db = new Database();
    $recorder = new SimpleRecorder();
    
    // 真实的抖音FLV地址
    $realFlvUrl = 'http://pull-flv-l26.douyincdn.com/stage/stream-117942867085230219_or4.flv?arch_hrchy=w1&exp_hrchy=w1&expire=68ca7511&major_anchor_level=common&sign=8dedf99c273092e6389e3dbbad9ed1b2&t_id=037-20250910164505061DD0AF4B1E4DCD2B27-8zG4Wv&unique_id=stream-117942867085230219_139_flv_or4';
    
    echo "真实FLV地址: $realFlvUrl\n";
    echo "==================\n\n";
    
    // 测试参数
    $orderId = 888;
    $maxDuration = 30; // 录制30秒
    
    echo "测试参数:\n";
    echo "订单ID: $orderId\n";
    echo "最大时长: {$maxDuration}秒\n\n";
    
    // 开始录制
    echo "开始录制真实抖音FLV...\n";
    $result = $recorder->recordVideo($orderId, $realFlvUrl, $maxDuration);
    
    if ($result['success']) {
        echo "\n🎉 录制成功！\n";
        echo "文件路径: {$result['file_path']}\n";
        echo "文件大小: " . $recorder->formatBytes($result['file_size']) . "\n";
        echo "视频时长: {$result['duration']}秒\n";
        
        // 检查文件是否真的存在
        if (file_exists($result['file_path'])) {
            echo "✅ 文件确实存在\n";
            
            // 获取视频详细信息
            $info = $recorder->getVideoInfo($result['file_path']);
            if ($info) {
                echo "视频信息:\n";
                echo "  分辨率: {$info['width']}x{$info['height']}\n";
                echo "  时长: {$info['duration']}秒\n";
                echo "  文件大小: " . $recorder->formatBytes($info['size']) . "\n";
                echo "  码率: {$info['bitrate']} bps\n";
                echo "  编码: {$info['codec']}\n";
            }
            
            // 测试播放
            echo "\n测试视频播放...\n";
            $playCommand = "ffplay -t 5 -autoexit " . escapeshellarg($result['file_path']) . " 2>/dev/null";
            $playResult = shell_exec($playCommand);
            echo "播放测试完成\n";
            
        } else {
            echo "❌ 文件不存在\n";
        }
        
    } else {
        echo "\n❌ 录制失败: {$result['error']}\n";
    }
    
    // 清理
    echo "\n清理测试文件...\n";
    $recorder->cleanup($orderId);
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
