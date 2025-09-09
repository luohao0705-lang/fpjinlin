<?php
// 检查任务状态
require_once 'config/config.php';
require_once 'config/database.php';

$db = new Database();

echo "<h2>任务状态检查</h2>";

try {
    $orderId = $_GET['order_id'] ?? 27;
    echo "<p>检查订单ID: {$orderId}</p>";
    
    // 检查订单状态
    $order = $db->fetchOne(
        "SELECT * FROM video_analysis_orders WHERE id = ?",
        [$orderId]
    );
    
    if ($order) {
        echo "<p>订单状态: " . htmlspecialchars($order['status']) . "</p>";
        echo "<p>订单标题: " . htmlspecialchars($order['title']) . "</p>";
    }
    
    // 检查视频文件状态
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? ORDER BY id",
        [$orderId]
    );
    
    echo "<h3>视频文件状态</h3>";
    foreach ($videoFiles as $file) {
        echo "<p>文件ID: {$file['id']}, 类型: {$file['file_type']}, 状态: {$file['status']}, 录制进度: {$file['recording_progress']}%</p>";
    }
    
    // 检查处理任务
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue WHERE order_id = ? ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    echo "<h3>处理任务状态</h3>";
    echo "<p>总任务数: " . count($tasks) . "</p>";
    
    $statusCounts = [];
    foreach ($tasks as $task) {
        $status = $task['status'];
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        
        echo "<p>任务ID: {$task['id']}, 类型: {$task['task_type']}, 状态: {$task['status']}, 优先级: {$task['priority']}</p>";
    }
    
    echo "<h3>状态统计</h3>";
    foreach ($statusCounts as $status => $count) {
        echo "<p>{$status}: {$count} 个</p>";
    }
    
    // 检查是否有待处理的任务
    $pendingTasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue WHERE order_id = ? AND status = 'pending' ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    if (!empty($pendingTasks)) {
        echo "<h3>待处理任务</h3>";
        echo "<p>找到 " . count($pendingTasks) . " 个待处理任务</p>";
        
        foreach ($pendingTasks as $task) {
            echo "<p>- 任务ID: {$task['id']}, 类型: {$task['task_type']}, 优先级: {$task['priority']}</p>";
        }
        
        echo "<p><a href='process_tasks_manually.php?order_id={$orderId}'>手动处理任务</a></p>";
    } else {
        echo "<p>✅ 没有待处理的任务</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
