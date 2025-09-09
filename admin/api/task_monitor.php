<?php
/**
 * 任务状态监控API
 * 复盘精灵系统 - 后台管理
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查管理员登录
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('请先登录');
    }
    
    $orderId = $_GET['order_id'] ?? 0;
    if (!$orderId) {
        throw new Exception('订单ID不能为空');
    }
    
    $db = new Database();
    
    // 获取订单信息
    $order = $db->fetchOne(
        "SELECT * FROM video_analysis_orders WHERE id = ?",
        [$orderId]
    );
    
    if (!$order) {
        throw new Exception('订单不存在');
    }
    
    // 获取所有任务
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue WHERE order_id = ? ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    // 获取视频文件信息
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
        [$orderId]
    );
    
    // 统计任务状态
    $taskStats = [
        'total' => count($tasks),
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0
    ];
    
    foreach ($tasks as $task) {
        $taskStats[$task['status']]++;
    }
    
    // 计算进度百分比
    $progress = $taskStats['total'] > 0 ? round(($taskStats['completed'] / $taskStats['total']) * 100) : 0;
    
    // 获取当前处理的任务
    $currentTask = $db->fetchOne(
        "SELECT * FROM video_processing_queue WHERE order_id = ? AND status = 'processing' ORDER BY started_at DESC LIMIT 1",
        [$orderId]
    );
    
    // 获取失败的任务
    $failedTasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue WHERE order_id = ? AND status = 'failed' ORDER BY created_at DESC",
        [$orderId]
    );
    
    echo json_encode([
        'success' => true,
        'data' => [
            'order' => $order,
            'tasks' => $tasks,
            'video_files' => $videoFiles,
            'task_stats' => $taskStats,
            'progress' => $progress,
            'current_task' => $currentTask,
            'failed_tasks' => $failedTasks,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
