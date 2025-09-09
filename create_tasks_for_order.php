<?php
/**
 * 为指定订单手动创建处理任务
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$orderId = intval($_GET['order_id'] ?? 23);

try {
    $db = new Database();
    
    echo "为订单 {$orderId} 创建处理任务...\n";
    
    // 获取视频文件
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
        [$orderId]
    );
    
    echo "找到 " . count($videoFiles) . " 个视频文件\n";
    
    // 删除现有任务
    $db->query("DELETE FROM video_processing_queue WHERE order_id = ?", [$orderId]);
    echo "删除现有任务\n";
    
    // 为每个视频文件创建处理任务
    foreach ($videoFiles as $videoFile) {
        echo "为视频文件 {$videoFile['id']} 创建任务...\n";
        
        // 1. 录制任务
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, 'record', ?, 1, 'pending', NOW())",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
        
        // 2. 转码任务
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, 'transcode', ?, 2, 'pending', NOW())",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
        
        // 3. 切片任务
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, 'segment', ?, 3, 'pending', NOW())",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
        
        // 4. ASR任务
        $db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, 'asr', ?, 4, 'pending', NOW())",
            [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
        );
    }
    
    // 5. 分析任务
    $db->insert(
        "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, 'analysis', ?, 5, 'pending', NOW())",
        [$orderId, json_encode(['order_id' => $orderId])]
    );
    
    // 6. 报告任务
    $db->insert(
        "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, 'report', ?, 6, 'pending', NOW())",
        [$orderId, json_encode(['order_id' => $orderId])]
    );
    
    // 统计创建的任务
    $taskCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM video_processing_queue WHERE order_id = ?",
        [$orderId]
    )['count'];
    
    echo "成功创建 {$taskCount} 个处理任务\n";
    
    // 显示任务列表
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue WHERE order_id = ? ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    echo "\n任务列表:\n";
    foreach ($tasks as $task) {
        echo "- {$task['task_type']} (优先级: {$task['priority']}, 状态: {$task['status']})\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
