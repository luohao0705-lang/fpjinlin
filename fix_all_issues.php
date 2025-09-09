<?php
// 修复所有发现的问题
require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>修复所有发现的问题</h2>";

try {
    $orderId = $_GET['order_id'] ?? 27;
    echo "<p>修复订单ID: {$orderId}</p>";
    
    $db = new Database();
    
    // 1. 修复任务优先级问题
    echo "<h3>1. 修复任务优先级</h3>";
    
    // 删除现有任务
    $db->query("DELETE FROM video_processing_queue WHERE order_id = ?", [$orderId]);
    echo "<p>✅ 删除现有任务</p>";
    
    // 获取视频文件
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? ORDER BY id",
        [$orderId]
    );
    
    // 重新创建任务，使用正确的优先级
    foreach ($videoFiles as $videoFile) {
        // 录制任务 - 最高优先级 (100)
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'record', ?, 100, 'pending')",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
        
        // 转码任务 (80)
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'transcode', ?, 80, 'pending')",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
        
        // 切片任务 (60)
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'segment', ?, 60, 'pending')",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
        
        // 语音识别任务 (40)
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'asr', ?, 40, 'pending')",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
    }
    
    // 全局任务
    $db->insert(
        "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'analysis', ?, 20, 'pending')",
        [$orderId, json_encode(['order_id' => $orderId])]
    );
    
    $db->insert(
        "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'report', ?, 10, 'pending')",
        [$orderId, json_encode(['order_id' => $orderId])]
    );
    
    echo "<p>✅ 重新创建任务，使用正确优先级</p>";
    
    // 2. 显示任务列表（按正确顺序）
    echo "<h3>2. 任务列表（按优先级排序）</h3>";
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? 
         ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    foreach ($tasks as $task) {
        $priorityText = '';
        switch ($task['priority']) {
            case 100: $priorityText = '录制'; break;
            case 80: $priorityText = '转码'; break;
            case 60: $priorityText = '切片'; break;
            case 40: $priorityText = '语音识别'; break;
            case 20: $priorityText = 'AI分析'; break;
            case 10: $priorityText = '报告生成'; break;
        }
        
        echo "<p>任务ID: {$task['id']}, 类型: {$task['task_type']}, 优先级: {$task['priority']} ({$priorityText}), 状态: {$task['status']}</p>";
    }
    
    // 3. 检查视频文件状态
    echo "<h3>3. 视频文件状态</h3>";
    foreach ($videoFiles as $file) {
        echo "<p>文件ID: {$file['id']}, 类型: {$file['file_type']}, 状态: {$file['status']}, FLV地址: " . 
             (empty($file['flv_url']) ? '未设置' : '已设置') . "</p>";
    }
    
    // 4. 开始处理任务
    echo "<h3>4. 开始处理任务</h3>";
    echo "<p><a href='process_tasks_correct.php?order_id={$orderId}'>开始处理任务（正确顺序）</a></p>";
    
    echo "<h3>5. 问题修复总结</h3>";
    echo "<ul>";
    echo "<li>✅ 修复了任务优先级问题（录制=100，报告=10）</li>";
    echo "<li>✅ 使用正确的排序顺序（priority DESC）</li>";
    echo "<li>✅ 重新创建了所有任务</li>";
    echo "<li>✅ 确保录制任务有最高优先级</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
