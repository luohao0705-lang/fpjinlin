<?php
/**
 * 重置视频分析任务
 * 清理失败的任务并重新创建
 */
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = new Database();
    
    echo "<h2>视频分析任务重置工具</h2>";
    
    $orderId = intval($_GET['order_id'] ?? 19); // 默认订单ID 19
    
    echo "<h3>订单ID: {$orderId}</h3>";
    
    // 1. 显示当前任务状态
    echo "<h4>当前任务状态</h4>";
    $stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'retry' THEN 1 ELSE 0 END) as retry
        FROM video_processing_queue
        WHERE order_id = ?
    ", [$orderId]);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>状态</th><th>数量</th></tr>";
    echo "<tr><td>总计</td><td>{$stats['total']}</td></tr>";
    echo "<tr><td>待处理</td><td>{$stats['pending']}</td></tr>";
    echo "<tr><td>处理中</td><td>{$stats['processing']}</td></tr>";
    echo "<tr><td>已完成</td><td>{$stats['completed']}</td></tr>";
    echo "<tr><td>失败</td><td>{$stats['failed']}</td></tr>";
    echo "<tr><td>重试</td><td>{$stats['retry']}</td></tr>";
    echo "</table>";
    
    // 2. 清理现有任务
    echo "<h4>清理现有任务</h4>";
    $deleted = $db->query("DELETE FROM video_processing_queue WHERE order_id = ?", [$orderId]);
    echo "✅ 删除了 {$deleted} 个现有任务<br>";
    
    // 3. 获取视频文件信息
    $videoFiles = $db->fetchAll(
        "SELECT id, video_type, video_index FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
        [$orderId]
    );
    
    echo "<h4>视频文件信息</h4>";
    echo "<ul>";
    foreach ($videoFiles as $file) {
        echo "<li>ID: {$file['id']}, 类型: {$file['video_type']}, 索引: {$file['video_index']}</li>";
    }
    echo "</ul>";
    
    // 4. 重新创建任务
    echo "<h4>重新创建任务</h4>";
    
    $tasks = [
        ['type' => 'record', 'priority' => 10],
        ['type' => 'transcode', 'priority' => 9],
        ['type' => 'segment', 'priority' => 8],
        ['type' => 'asr', 'priority' => 7],
        ['type' => 'analysis', 'priority' => 6],
        ['type' => 'report', 'priority' => 5]
    ];
    
    $createdCount = 0;
    
    foreach ($tasks as $task) {
        if ($task['type'] === 'record') {
            // 为每个视频文件创建录制任务
            foreach ($videoFiles as $videoFile) {
                $db->insert(
                    "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                    [$orderId, $task['type'], json_encode(['video_file_id' => $videoFile['id']]), $task['priority']]
                );
                $createdCount++;
                echo "✅ 创建录制任务: 视频文件ID {$videoFile['id']}<br>";
            }
        } else {
            // 其他任务只创建一次
            $db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                [$orderId, $task['type'], json_encode([]), $task['priority']]
            );
            $createdCount++;
            echo "✅ 创建{$task['type']}任务<br>";
        }
    }
    
    echo "<p><strong>总共创建了 {$createdCount} 个任务</strong></p>";
    
    // 5. 显示重置后的状态
    echo "<h4>重置后任务状态</h4>";
    $newStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'retry' THEN 1 ELSE 0 END) as retry
        FROM video_processing_queue
        WHERE order_id = ?
    ", [$orderId]);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>状态</th><th>数量</th></tr>";
    echo "<tr><td>总计</td><td>{$newStats['total']}</td></tr>";
    echo "<tr><td>待处理</td><td>{$newStats['pending']}</td></tr>";
    echo "<tr><td>处理中</td><td>{$newStats['processing']}</td></tr>";
    echo "<tr><td>已完成</td><td>{$newStats['completed']}</td></tr>";
    echo "<tr><td>失败</td><td>{$newStats['failed']}</td></tr>";
    echo "<tr><td>重试</td><td>{$newStats['retry']}</td></tr>";
    echo "</table>";
    
    echo "<h4>重置完成！</h4>";
    echo "<p><a href='admin/video_order_detail.php?id={$orderId}'>返回订单详情</a></p>";
    echo "<p><a href='test_recording_progress.php?order_id={$orderId}'>测试录制进度</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
