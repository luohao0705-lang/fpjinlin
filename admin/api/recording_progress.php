<?php
/**
 * 录制进度查询API
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
    
    $orderId = intval($_GET['order_id'] ?? 0);
    if (!$orderId) {
        throw new Exception('订单ID不能为空');
    }
    
    $db = new Database();
    
    // 获取订单的视频文件信息
    $videoFiles = $db->fetchAll(
        "SELECT vf.*, vao.title as order_title 
         FROM video_files vf 
         JOIN video_analysis_orders vao ON vf.order_id = vao.id 
         WHERE vf.order_id = ? 
         ORDER BY vf.video_type, vf.video_index",
        [$orderId]
    );
    
    if (empty($videoFiles)) {
        echo json_encode([
            'success' => true,
            'message' => '没有找到视频文件',
            'data' => []
        ]);
        return;
    }
    
    $result = [];
    
    foreach ($videoFiles as $videoFile) {
        // 获取最新的进度日志
        $latestProgress = $db->fetchOne(
            "SELECT * FROM recording_progress_logs 
             WHERE video_file_id = ? 
             ORDER BY created_at DESC 
             LIMIT 1",
            [$videoFile['id']]
        );
        
        // 获取进度历史（最近10条）
        $progressHistory = $db->fetchAll(
            "SELECT progress, message, duration, file_size, created_at 
             FROM recording_progress_logs 
             WHERE video_file_id = ? 
             ORDER BY created_at DESC 
             LIMIT 10",
            [$videoFile['id']]
        );
        
        $result[] = [
            'video_file_id' => $videoFile['id'],
            'video_type' => $videoFile['video_type'],
            'video_index' => $videoFile['video_index'],
            'flv_url' => $videoFile['flv_url'],
            'recording_status' => $videoFile['recording_status'] ?? 'pending',
            'recording_progress' => intval($videoFile['recording_progress'] ?? 0),
            'recording_started_at' => $videoFile['recording_started_at'],
            'recording_completed_at' => $videoFile['recording_completed_at'],
            'file_size' => $videoFile['file_size'],
            'duration' => $videoFile['duration'],
            'resolution' => $videoFile['resolution'],
            'latest_progress' => $latestProgress,
            'progress_history' => $progressHistory
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("录制进度查询失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
