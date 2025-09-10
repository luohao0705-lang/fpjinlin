<?php
/**
 * 测试统一视频处理系统
 */

require_once 'config/database.php';
require_once 'UnifiedVideoProcessor.php';

echo "🧪 测试统一视频处理系统\n";
echo "==================\n\n";

try {
    $db = new Database();
    $processor = new UnifiedVideoProcessor();
    
    // 1. 获取一个现有订单进行测试
    $order = $db->fetchOne(
        "SELECT * FROM video_analysis_orders ORDER BY id DESC LIMIT 1"
    );
    
    if (!$order) {
        echo "❌ 没有找到任何订单，创建一个测试订单...\n";
        
        // 创建测试订单
        $orderId = $db->insert(
            "INSERT INTO video_analysis_orders (user_id, order_no, title, self_video_link, cost_coins, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                1, 
                'VA' . date('YmdHis') . rand(1000, 9999),
                '统一系统测试订单',
                'https://live.douyin.com/test',
                50,
                'reviewing'
            ]
        );
        
        // 创建视频文件记录
        $db->insert(
            "INSERT INTO video_files (order_id, video_type, video_index, original_url, status, recording_status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $orderId,
                'self',
                0,
                'https://live.douyin.com/test',
                'pending',
                'pending'
            ]
        );
        
        echo "✅ 创建测试订单: ID $orderId\n\n";
    } else {
        $orderId = $order['id'];
        echo "📋 使用现有订单: ID $orderId\n";
        echo "订单状态: {$order['status']}\n";
        echo "FLV地址: " . ($order['self_flv_url'] ? '有' : '无') . "\n\n";
    }
    
    // 2. 测试统一处理器
    echo "🚀 启动统一视频处理系统...\n";
    $result = $processor->startAnalysis($orderId);
    
    if ($result['success']) {
        echo "✅ 处理成功: " . $result['message'] . "\n";
    } else {
        echo "❌ 处理失败: " . $result['message'] . "\n";
    }
    
    // 3. 检查最终状态
    echo "\n🔍 检查最终状态...\n";
    $finalOrder = $db->fetchOne(
        "SELECT * FROM video_analysis_orders WHERE id = ?",
        [$orderId]
    );
    
    if ($finalOrder) {
        echo "订单状态: {$finalOrder['status']}\n";
        echo "错误信息: " . ($finalOrder['error_message'] ?: '无') . "\n";
    }
    
    // 4. 检查视频文件
    $videoFile = $db->fetchOne(
        "SELECT * FROM video_files WHERE order_id = ? AND video_type = 'self'",
        [$orderId]
    );
    
    if ($videoFile) {
        echo "视频文件状态: {$videoFile['status']}\n";
        echo "录制状态: {$videoFile['recording_status']}\n";
        echo "文件路径: " . ($videoFile['file_path'] ?: '无') . "\n";
        echo "文件大小: " . ($videoFile['file_size'] ? $this->formatBytes($videoFile['file_size']) : '无') . "\n";
        echo "视频时长: " . ($videoFile['duration'] ?: '无') . "秒\n";
    }
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . "\n";
    echo "行号: " . $e->getLine() . "\n";
}

/**
 * 格式化字节数
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}
?>
