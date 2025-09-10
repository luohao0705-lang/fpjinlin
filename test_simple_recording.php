<?php
/**
 * 测试简化录制流程
 */

require_once 'config/database.php';
require_once 'FastRecorder.php';

echo "🧪 测试简化录制流程\n";
echo "==================\n\n";

try {
    $db = new Database();
    $recorder = new FastRecorder();
    
    // 1. 创建测试订单
    echo "1. 创建测试订单...\n";
    $orderId = $db->insert(
        "INSERT INTO video_analysis_orders (user_id, order_no, title, self_video_link, self_flv_url, cost_coins, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            1, 
            'TEST' . date('YmdHis') . rand(1000, 9999),
            '简化录制测试',
            'https://live.douyin.com/test',
            'https://live.douyin.com/test?expire=' . (time() + 3600),
            50,
            'pending'
        ]
    );
    
    echo "✅ 创建测试订单: ID $orderId\n\n";
    
    // 2. 开始录制
    echo "2. 开始录制...\n";
    $result = $recorder->recordVideo($orderId, 'https://live.douyin.com/test?expire=' . (time() + 3600), 60);
    
    if ($result['success']) {
        echo "✅ 录制成功！\n";
        echo "文件路径: {$result['file_path']}\n";
        echo "文件大小: " . $recorder->formatBytes($result['file_size']) . "\n";
        echo "视频时长: {$result['duration']}秒\n";
    } else {
        echo "❌ 录制失败: {$result['error']}\n";
    }
    
    // 3. 检查数据库状态
    echo "\n3. 检查数据库状态...\n";
    $order = $db->fetchOne("SELECT * FROM video_analysis_orders WHERE id = ?", [$orderId]);
    echo "订单状态: {$order['status']}\n";
    
    $videoFiles = $db->fetchAll("SELECT * FROM video_files WHERE order_id = ?", [$orderId]);
    echo "视频文件数量: " . count($videoFiles) . "\n";
    
    if (!empty($videoFiles)) {
        $videoFile = $videoFiles[0];
        echo "视频文件状态: {$videoFile['status']}\n";
        echo "录制状态: {$videoFile['recording_status']}\n";
        echo "文件大小: " . $recorder->formatBytes($videoFile['file_size']) . "\n";
        echo "视频时长: {$videoFile['duration']}秒\n";
    }
    
    // 4. 清理测试数据（可选）
    echo "\n4. 清理测试数据...\n";
    $recorder->cleanupRecording($orderId);
    $db->query("DELETE FROM video_files WHERE order_id = ?", [$orderId]);
    $db->query("DELETE FROM video_analysis_orders WHERE id = ?", [$orderId]);
    echo "✅ 测试数据已清理\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
