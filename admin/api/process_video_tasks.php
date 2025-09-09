<?php
/**
 * 手动处理视频任务API
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
    
    // 获取待处理的任务
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? AND status = 'pending' 
         ORDER BY priority DESC, created_at ASC 
         LIMIT 1",
        [$orderId]
    );
    
    if (empty($tasks)) {
        echo json_encode([
            'success' => true,
            'message' => '没有待处理的任务',
            'processed' => 0
        ]);
        return;
    }
    
    $processed = 0;
    
    foreach ($tasks as $task) {
        try {
            // 更新任务状态为处理中
            $db->query(
                "UPDATE video_processing_queue SET status = 'processing', started_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            // 模拟任务处理（实际项目中应该调用相应的处理类）
            $taskData = json_decode($task['task_data'], true);
            
            // 根据任务类型进行处理
            switch ($task['task_type']) {
                case 'record':
                    // 录制FLV流
                    $videoFile = $db->fetchOne(
                        "SELECT * FROM video_files WHERE id = ?",
                        [$taskData['video_file_id']]
                    );
                    
                    if ($videoFile && $videoFile['flv_url']) {
                        require_once '../../includes/classes/VideoProcessor.php';
                        $videoProcessor = new VideoProcessor();
                        $videoProcessor->recordVideo($videoFile['id'], $videoFile['flv_url']);
                    } else {
                        throw new Exception('视频文件或FLV地址不存在');
                    }
                    break;
                    
                case 'transcode':
                    // 转码处理
                    $db->query(
                        "UPDATE video_files SET status = 'completed' WHERE id = ?",
                        [$taskData['video_file_id']]
                    );
                    break;
                    
                case 'segment':
                    // 切片处理
                    $db->query(
                        "UPDATE video_files SET status = 'completed' WHERE id = ?",
                        [$taskData['video_file_id']]
                    );
                    break;
                    
                case 'asr':
                    // 语音识别处理
                    break;
                    
                case 'analysis':
                    // 视频分析处理
                    break;
            }
            
            // 更新任务状态为完成
            $db->query(
                "UPDATE video_processing_queue SET status = 'completed', completed_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            $processed++;
            
        } catch (Exception $e) {
            // 更新任务状态为失败
            $db->query(
                "UPDATE video_processing_queue SET status = 'failed', error_message = ? WHERE id = ?",
                [$e->getMessage(), $task['id']]
            );
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "处理了 {$processed} 个任务",
        'processed' => $processed
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
