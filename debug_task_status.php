<?php
/**
 * 调试任务状态脚本
 */

require_once 'config/database.php';

echo "🔍 调试任务状态\n";
echo "==============\n\n";

try {
    $db = new Database();
    
    // 1. 检查视频分析订单
    echo "1. 视频分析订单状态:\n";
    $orders = $db->fetchAll("SELECT id, status, self_flv_url, created_at FROM video_analysis_orders ORDER BY id DESC LIMIT 5");
    foreach ($orders as $order) {
        echo "订单ID: {$order['id']}, 状态: {$order['status']}, FLV: " . (empty($order['self_flv_url']) ? '无' : '有') . ", 创建时间: {$order['created_at']}\n";
    }
    
    // 2. 检查视频文件
    echo "\n2. 视频文件状态:\n";
    $videos = $db->fetchAll("SELECT id, order_id, status, flv_url, recording_status FROM video_files ORDER BY id DESC LIMIT 5");
    foreach ($videos as $video) {
        echo "视频ID: {$video['id']}, 订单ID: {$video['order_id']}, 状态: {$video['status']}, 录制状态: {$video['recording_status']}, FLV: " . (empty($video['flv_url']) ? '无' : '有') . "\n";
    }
    
    // 3. 检查处理队列
    echo "\n3. 处理队列状态:\n";
    $tasks = $db->fetchAll("SELECT id, order_id, task_type, status, error_message, created_at FROM video_processing_queue ORDER BY id DESC LIMIT 10");
    foreach ($tasks as $task) {
        echo "任务ID: {$task['id']}, 订单ID: {$task['order_id']}, 类型: {$task['task_type']}, 状态: {$task['status']}, 错误: " . ($task['error_message'] ?: '无') . ", 创建时间: {$task['created_at']}\n";
    }
    
    // 4. 检查系统配置
    echo "\n4. 系统配置:\n";
    $configs = $db->fetchAll("SELECT config_key, config_value FROM system_config WHERE config_key IN ('max_concurrent_processing', 'max_video_duration', 'video_segment_duration')");
    foreach ($configs as $config) {
        echo "{$config['config_key']}: {$config['config_value']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}
?>
