<?php
/**
 * 视频分析进度监控API
 * 复盘精灵系统 - 后台管理
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查管理员登录
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('未授权访问');
    }
    
    $orderId = intval($_GET['order_id'] ?? 0);
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
    
    // 获取视频文件处理状态
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
        [$orderId]
    );
    
    // 获取处理任务状态
    $processingTasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue WHERE order_id = ? ORDER BY created_at DESC",
        [$orderId]
    );
    
    // 调试信息
    error_log("视频分析进度API调试 - 订单ID: {$orderId}, 视频文件数: " . count($videoFiles) . ", 任务数: " . count($processingTasks));
    
    // 计算总体进度
    $totalTasks = count($processingTasks);
    $completedTasks = count(array_filter($processingTasks, function($task) {
        return $task['status'] === 'completed';
    }));
    $failedTasks = count(array_filter($processingTasks, function($task) {
        return $task['status'] === 'failed';
    }));
    $processingCount = count(array_filter($processingTasks, function($task) {
        return $task['status'] === 'processing';
    }));
    
    $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
    
    // 生成进度HTML
    $html = '<div class="progress mb-3" style="height: 25px;">';
    $html .= '<div class="progress-bar" style="width: ' . $progressPercentage . '%">';
    $html .= $progressPercentage . '%';
    $html .= '</div></div>';
    
    $html .= '<div class="row text-center">';
    $html .= '<div class="col-3"><small class="text-muted">总任务</small><br><strong>' . $totalTasks . '</strong></div>';
    $html .= '<div class="col-3"><small class="text-success">已完成</small><br><strong>' . $completedTasks . '</strong></div>';
    $html .= '<div class="col-3"><small class="text-primary">处理中</small><br><strong>' . $processingCount . '</strong></div>';
    $html .= '<div class="col-3"><small class="text-danger">失败</small><br><strong>' . $failedTasks . '</strong></div>';
    $html .= '</div>';
    
    // 显示当前处理的任务
    if ($processingCount > 0) {
        $currentTask = array_filter($processingTasks, function($task) {
            return $task['status'] === 'processing';
        });
        $currentTask = reset($currentTask);
        
        $html .= '<div class="mt-3">';
        $html .= '<small class="text-muted">当前处理：</small> ';
        $html .= '<span class="badge bg-primary">' . htmlspecialchars($currentTask['task_type']) . '</span>';
        $html .= '</div>';
    }
    
    // 显示视频文件状态
    $html .= '<div class="mt-3">';
    $html .= '<h6>视频文件状态</h6>';
    $html .= '<div class="row">';
    
    foreach ($videoFiles as $file) {
        $statusClass = '';
        $statusText = '';
        
        switch ($file['status']) {
            case 'pending':
                $statusClass = 'bg-secondary';
                $statusText = '待处理';
                break;
            case 'downloading':
                $statusClass = 'bg-primary';
                $statusText = '下载中';
                break;
            case 'processing':
                $statusClass = 'bg-warning';
                $statusText = '处理中';
                break;
            case 'completed':
                $statusClass = 'bg-success';
                $statusText = '已完成';
                break;
            case 'failed':
                $statusClass = 'bg-danger';
                $statusText = '失败';
                break;
        }
        
        $videoTypeText = $file['video_type'] === 'self' ? '本方' : '同行' . $file['video_index'];
        
        $html .= '<div class="col-md-6 mb-2">';
        $html .= '<div class="d-flex justify-content-between align-items-center">';
        $html .= '<span>' . $videoTypeText . '</span>';
        $html .= '<span class="badge ' . $statusClass . '">' . $statusText . '</span>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div></div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'progress' => $progressPercentage,
        'status' => $order['status'],
        'data' => [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'processing_tasks' => $processingCount,
            'failed_tasks' => $failedTasks
        ]
    ]);
    
} catch (Exception $e) {
    error_log("视频分析进度API错误: " . $e->getMessage() . " 文件: " . $e->getFile() . " 行: " . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
