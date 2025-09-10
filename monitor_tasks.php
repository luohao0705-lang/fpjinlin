<?php
/**
 * 监控任务处理状态
 */

require_once 'config/database.php';

echo "📊 任务处理监控\n";
echo "==============\n\n";

try {
    $db = new Database();
    
    // 1. 检查待处理任务
    echo "1. 待处理任务:\n";
    $pendingTasks = $db->fetchAll("
        SELECT t.id, t.order_id, t.task_type, t.status, t.created_at, o.self_flv_url 
        FROM video_processing_queue t 
        LEFT JOIN video_analysis_orders o ON t.order_id = o.id 
        WHERE t.status = 'pending' 
        ORDER BY t.priority DESC, t.created_at ASC
    ");
    
    if (empty($pendingTasks)) {
        echo "✅ 没有待处理任务\n";
    } else {
        echo "⚠️ 有 " . count($pendingTasks) . " 个待处理任务:\n";
        foreach ($pendingTasks as $task) {
            echo "  - 任务ID: {$task['id']}, 订单ID: {$task['order_id']}, 类型: {$task['task_type']}, FLV: " . (empty($task['self_flv_url']) ? '无' : '有') . "\n";
        }
    }
    
    // 2. 检查处理中任务
    echo "\n2. 处理中任务:\n";
    $processingTasks = $db->fetchAll("
        SELECT t.id, t.order_id, t.task_type, t.status, t.created_at, t.error_message 
        FROM video_processing_queue t 
        WHERE t.status = 'processing' 
        ORDER BY t.created_at ASC
    ");
    
    if (empty($processingTasks)) {
        echo "✅ 没有处理中任务\n";
    } else {
        echo "⚠️ 有 " . count($processingTasks) . " 个处理中任务:\n";
        foreach ($processingTasks as $task) {
            echo "  - 任务ID: {$task['id']}, 订单ID: {$task['order_id']}, 类型: {$task['task_type']}, 错误: " . ($task['error_message'] ?: '无') . "\n";
        }
    }
    
    // 3. 检查失败任务
    echo "\n3. 失败任务:\n";
    $failedTasks = $db->fetchAll("
        SELECT t.id, t.order_id, t.task_type, t.status, t.error_message, t.created_at 
        FROM video_processing_queue t 
        WHERE t.status = 'failed' 
        ORDER BY t.created_at DESC 
        LIMIT 5
    ");
    
    if (empty($failedTasks)) {
        echo "✅ 没有失败任务\n";
    } else {
        echo "❌ 有 " . count($failedTasks) . " 个失败任务:\n";
        foreach ($failedTasks as $task) {
            echo "  - 任务ID: {$task['id']}, 订单ID: {$task['order_id']}, 类型: {$task['task_type']}, 错误: {$task['error_message']}\n";
        }
    }
    
    // 4. 检查系统资源
    echo "\n4. 系统资源:\n";
    $cpuLoad = sys_getloadavg()[0];
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    
    echo "CPU负载: $cpuLoad\n";
    echo "内存使用: " . number_format($memoryUsage / 1024 / 1024, 2) . " MB\n";
    echo "内存限制: $memoryLimit\n";
    
    // 5. 检查FFmpeg进程
    echo "\n5. FFmpeg进程:\n";
    $output = [];
    exec("ps aux | grep ffmpeg | grep -v grep | wc -l", $output);
    $ffmpegCount = intval($output[0]);
    echo "当前FFmpeg进程数: $ffmpegCount\n";
    
    if ($ffmpegCount > 0) {
        exec("ps aux | grep ffmpeg | grep -v grep", $ffmpegProcesses);
        echo "FFmpeg进程详情:\n";
        foreach ($ffmpegProcesses as $process) {
            echo "  - $process\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}
?>
