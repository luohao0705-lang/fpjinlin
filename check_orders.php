<?php
// 检查订单是否被创建
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = new Database();
    
    // 检查video_analysis_orders表
    echo "=== 视频分析订单 ===\n";
    $videoOrders = $db->fetchAll("SELECT * FROM video_analysis_orders ORDER BY created_at DESC LIMIT 5");
    foreach ($videoOrders as $order) {
        echo "ID: {$order['id']}, 订单号: {$order['order_no']}, 标题: {$order['title']}, 状态: {$order['status']}, 用户ID: {$order['user_id']}, 创建时间: {$order['created_at']}\n";
    }
    
    // 检查analysis_orders表
    echo "\n=== 文本分析订单 ===\n";
    $textOrders = $db->fetchAll("SELECT * FROM analysis_orders ORDER BY created_at DESC LIMIT 5");
    foreach ($textOrders as $order) {
        echo "ID: {$order['id']}, 订单号: {$order['order_no']}, 标题: {$order['title']}, 状态: {$order['status']}, 用户ID: {$order['user_id']}, 创建时间: {$order['created_at']}\n";
    }
    
    // 检查video_files表
    echo "\n=== 视频文件记录 ===\n";
    $videoFiles = $db->fetchAll("SELECT * FROM video_files ORDER BY created_at DESC LIMIT 10");
    foreach ($videoFiles as $file) {
        echo "ID: {$file['id']}, 订单ID: {$file['order_id']}, 类型: {$file['video_type']}, 索引: {$file['video_index']}, 状态: {$file['status']}\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
