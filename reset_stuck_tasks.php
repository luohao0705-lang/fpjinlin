<?php
// 重置卡住的任务
require_once 'config/config.php';
require_once 'config/database.php';

$db = new Database();

echo "<h2>重置卡住的任务</h2>";

try {
    // 获取订单ID
    $orderId = $_GET['order_id'] ?? 25;
    
    echo "<p>处理订单ID: {$orderId}</p>";
    
    // 1. 重置所有处理中的任务为待处理
    $result1 = $db->query(
        "UPDATE video_processing_queue SET status = 'pending', started_at = NULL, completed_at = NULL, error_message = NULL WHERE order_id = ? AND status = 'processing'",
        [$orderId]
    );
    
    echo "<p>✅ 重置了 " . $result1->rowCount() . " 个处理中的任务</p>";
    
    // 2. 重置视频文件状态
    $result2 = $db->query(
        "UPDATE video_files SET status = 'pending', recording_progress = 0, recording_status = 'pending' WHERE order_id = ?",
        [$orderId]
    );
    
    echo "<p>✅ 重置了 " . $result2->rowCount() . " 个视频文件状态</p>";
    
    // 3. 删除录制进度日志
    $result3 = $db->query(
        "DELETE FROM recording_progress_logs WHERE video_file_id IN (SELECT id FROM video_files WHERE order_id = ?)",
        [$orderId]
    );
    
    echo "<p>✅ 删除了 " . $result3->rowCount() . " 条录制进度日志</p>";
    
    // 4. 显示当前任务状态
    $tasks = $db->fetchAll(
        "SELECT task_type, status, created_at FROM video_processing_queue WHERE order_id = ? ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    echo "<h3>当前任务状态:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>任务类型</th><th>状态</th><th>创建时间</th></tr>";
    
    foreach ($tasks as $task) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($task['task_type']) . "</td>";
        echo "<td>" . htmlspecialchars($task['status']) . "</td>";
        echo "<td>" . htmlspecialchars($task['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><a href='process_tasks_manually.php?order_id={$orderId}'>重新处理任务</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
