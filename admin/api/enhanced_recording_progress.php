<?php
/**
 * 增强版录制进度查询API
 * 包含实时视频流和录制计时器
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
    
    // 获取视频文件信息（去重）
    $videoFiles = $db->fetchAll(
        "SELECT vf.*, 
                CASE 
                    WHEN vf.video_type = 'self' THEN '本方视频'
                    WHEN vf.video_type = 'competitor' THEN CONCAT('同行视频', vf.video_index)
                    ELSE '未知类型'
                END as display_name
         FROM video_files vf 
         WHERE vf.order_id = ? 
         ORDER BY vf.video_type, vf.video_index",
        [$orderId]
    );
    
    // 获取工作流进度日志
    $workflowLogs = $db->fetchAll(
        "SELECT * FROM workflow_progress_logs 
         WHERE order_id = ? 
         ORDER BY created_at DESC 
         LIMIT 20",
        [$orderId]
    );
    
    // 获取处理队列状态
    $queueTasks = $db->fetchAll(
        "SELECT task_type, status, COUNT(*) as count 
         FROM video_processing_queue 
         WHERE order_id = ? 
         GROUP BY task_type, status",
        [$orderId]
    );
    
    $result = [
        'order' => $order,
        'video_files' => [],
        'workflow_logs' => $workflowLogs,
        'queue_tasks' => $queueTasks,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    foreach ($videoFiles as $videoFile) {
        // 获取最新的进度日志
        $latestProgress = $db->fetchOne(
            "SELECT * FROM recording_progress_logs 
             WHERE video_file_id = ? 
             ORDER BY created_at DESC 
             LIMIT 1",
            [$videoFile['id']]
        );
        
        // 计算录制时长
        $recordingDuration = 0;
        if ($videoFile['recording_started_at']) {
            $startTime = strtotime($videoFile['recording_started_at']);
            if ($videoFile['recording_completed_at']) {
                $endTime = strtotime($videoFile['recording_completed_at']);
                $recordingDuration = $endTime - $startTime;
            } else {
                $recordingDuration = time() - $startTime;
            }
        }
        
        // 格式化录制时长
        $durationFormatted = formatDuration($recordingDuration);
        
        // 获取实时文件大小（如果文件存在）
        $realFileSize = 0;
        if ($videoFile['file_path'] && file_exists($videoFile['file_path'])) {
            $realFileSize = filesize($videoFile['file_path']);
        }
        
        $result['video_files'][] = [
            'id' => $videoFile['id'],
            'display_name' => $videoFile['display_name'],
            'video_type' => $videoFile['video_type'],
            'video_index' => $videoFile['video_index'],
            'flv_url' => $videoFile['flv_url'],
            'original_url' => $videoFile['original_url'],
            'recording_status' => $videoFile['recording_status'] ?? 'pending',
            'recording_progress' => intval($videoFile['recording_progress'] ?? 0),
            'recording_started_at' => $videoFile['recording_started_at'],
            'recording_completed_at' => $videoFile['recording_completed_at'],
            'recording_duration' => $recordingDuration,
            'recording_duration_formatted' => $durationFormatted,
            'file_size' => $videoFile['file_size'],
            'real_file_size' => $realFileSize,
            'file_size_formatted' => formatBytes($realFileSize ?: $videoFile['file_size']),
            'duration' => $videoFile['duration'],
            'resolution' => $videoFile['resolution'],
            'status' => $videoFile['status'],
            'latest_progress' => $latestProgress,
            'is_recording' => $videoFile['recording_status'] === 'recording',
            'is_completed' => $videoFile['recording_status'] === 'completed',
            'is_failed' => $videoFile['recording_status'] === 'failed'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    error_log("增强版录制进度查询失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * 格式化时长
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . '秒';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return $minutes . '分' . $remainingSeconds . '秒';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . '小时' . $minutes . '分钟';
    }
}

/**
 * 格式化文件大小
 */
function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}
?>
