<?php
/**
 * 只测试录制功能 - 极简版本
 */

require_once 'config/database.php';
require_once 'SimpleRecorder.php';

echo "🎬 录制功能测试\n";
echo "===============\n\n";

try {
    $db = new Database();
    $recorder = new SimpleRecorder();
    
    // 测试参数
    $orderId = 999; // 使用固定ID，避免数据库问题
    $flvUrl = 'https://live.douyin.com/test?expire=' . (time() + 3600);
    $maxDuration = 30; // 只录制30秒
    
    echo "测试参数:\n";
    echo "订单ID: $orderId\n";
    echo "FLV地址: $flvUrl\n";
    echo "最大时长: {$maxDuration}秒\n\n";
    
    // 开始录制
    echo "开始录制...\n";
    $result = $recorder->recordVideo($orderId, $flvUrl, $maxDuration);
    
    if ($result['success']) {
        echo "\n🎉 录制成功！\n";
        echo "文件路径: {$result['file_path']}\n";
        echo "文件大小: " . $recorder->formatBytes($result['file_size']) . "\n";
        echo "视频时长: {$result['duration']}秒\n";
        
        // 检查文件是否存在
        if (file_exists($result['file_path'])) {
            echo "✅ 文件确实存在\n";
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
