<?php
/**
 * 使用真实视频源测试录制功能
 */

require_once 'config/database.php';
require_once 'SimpleRecorder.php';

echo "🎬 使用真实视频源测试录制\n";
echo "========================\n\n";

try {
    $db = new Database();
    $recorder = new SimpleRecorder();
    
    // 使用公开的视频源进行测试
    $testVideoUrl = 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4';
    
    echo "测试视频URL: $testVideoUrl\n";
    echo "==================\n\n";
    
    // 创建测试订单
    $orderId = 777;
    
    echo "创建测试订单...\n";
    $orderId = $db->insert(
        "INSERT INTO video_analysis_orders (user_id, order_no, title, self_video_link, self_flv_url, cost_coins, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            1, 
            'REAL' . date('YmdHis') . rand(1000, 9999),
            '真实视频测试',
            $testVideoUrl,
            $testVideoUrl,
            50,
            'pending'
        ]
    );
    
    echo "✅ 测试订单ID: $orderId\n\n";
    
    // 开始录制
    echo "开始录制真实视频...\n";
    $result = $recorder->recordVideo($orderId, $testVideoUrl, 30);
    
    if ($result['success']) {
        echo "\n🎉 录制成功！\n";
        echo "文件路径: {$result['file_path']}\n";
        echo "文件大小: " . $recorder->formatBytes($result['file_size']) . "\n";
        echo "视频时长: {$result['duration']}秒\n";
        
        // 检查文件
        if (file_exists($result['file_path'])) {
            echo "✅ 文件确实存在\n";
            
            // 获取视频信息
            $info = $recorder->getVideoInfo($result['file_path']);
            if ($info) {
                echo "视频信息:\n";
                echo "  分辨率: {$info['width']}x{$info['height']}\n";
                echo "  时长: {$info['duration']}秒\n";
                echo "  编码: {$info['codec']}\n";
            }
        }
        
    } else {
        echo "\n❌ 录制失败: {$result['error']}\n";
    }
    
    // 清理
    echo "\n清理测试文件...\n";
    $recorder->cleanup($orderId);
    $db->query("DELETE FROM video_files WHERE order_id = ?", [$orderId]);
    $db->query("DELETE FROM video_analysis_orders WHERE id = ?", [$orderId]);
    echo "✅ 测试数据已清理\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
}
?>
