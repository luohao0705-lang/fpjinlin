<?php
// 修复视频分析流程
require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>修复视频分析流程</h2>";

try {
    $orderId = $_GET['order_id'] ?? 27;
    echo "<p>修复订单ID: {$orderId}</p>";
    
    $db = new Database();
    
    // 1. 重置所有任务状态
    echo "<h3>1. 重置任务状态</h3>";
    $db->query(
        "UPDATE video_processing_queue SET status = 'pending', started_at = NULL, completed_at = NULL, error_message = NULL WHERE order_id = ?",
        [$orderId]
    );
    echo "<p>✅ 所有任务已重置为待处理状态</p>";
    
    // 2. 检查视频文件状态
    echo "<h3>2. 检查视频文件状态</h3>";
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? ORDER BY id",
        [$orderId]
    );
    
    foreach ($videoFiles as $file) {
        echo "<p>文件ID: {$file['id']}, 类型: {$file['file_type']}, 状态: {$file['status']}, FLV地址: " . 
             (empty($file['flv_url']) ? '未设置' : '已设置') . "</p>";
    }
    
    // 3. 重新创建任务（按正确顺序）
    echo "<h3>3. 重新创建任务</h3>";
    
    // 删除现有任务
    $db->query("DELETE FROM video_processing_queue WHERE order_id = ?", [$orderId]);
    echo "<p>✅ 删除现有任务</p>";
    
    // 重新创建任务
    foreach ($videoFiles as $videoFile) {
        // 1. 录制任务 - 最高优先级
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'record', ?, 10, 'pending')",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
        
        // 2. 转码任务
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'transcode', ?, 8, 'pending')",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
        
        // 3. 切片任务
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'segment', ?, 6, 'pending')",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
        
        // 4. 语音识别任务
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'asr', ?, 4, 'pending')",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
    }
    
    // 5. 全局任务
    $db->insert(
        "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'analysis', ?, 2, 'pending')",
        [$orderId, json_encode(['order_id' => $orderId])]
    );
    
    $db->insert(
        "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'report', ?, 1, 'pending')",
        [$orderId, json_encode(['order_id' => $orderId])]
    );
    
    echo "<p>✅ 重新创建任务完成</p>";
    
    // 6. 显示任务列表
    echo "<h3>4. 任务列表（按优先级排序）</h3>";
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? 
         ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    foreach ($tasks as $task) {
        echo "<p>任务ID: {$task['id']}, 类型: {$task['task_type']}, 优先级: {$task['priority']}, 状态: {$task['status']}</p>";
    }
    
    echo "<h3>5. 开始处理任务</h3>";
    echo "<p><a href='process_tasks_fixed.php?order_id={$orderId}'>开始处理任务</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
