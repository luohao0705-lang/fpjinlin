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
    
    // 获取待处理的任务 - 处理所有待处理任务
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? AND status = 'pending' 
         ORDER BY priority DESC, created_at ASC",
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
                    // 真正录制FLV流
                    $videoFile = $db->fetchOne(
                        "SELECT * FROM video_files WHERE id = ?",
                        [$taskData['video_file_id']]
                    );
                    
                    if (!$videoFile) {
                        throw new Exception('视频文件不存在: ID ' . $taskData['video_file_id']);
                    }
                    
                    if (empty($videoFile['flv_url'])) {
                        throw new Exception('FLV地址为空，请先在后台配置FLV地址');
                    }
                    
                    try {
                        require_once '../../includes/classes/VideoProcessor.php';
                        $videoProcessor = new VideoProcessor();
                        
                        // 真正执行录制
                        $videoProcessor->recordVideo($videoFile['id'], $videoFile['flv_url']);
                        error_log("✅ 录制完成: 视频文件ID {$videoFile['id']}, FLV地址: {$videoFile['flv_url']}");
                        
                        // 更新任务状态为完成
                        $db->query(
                            "UPDATE video_processing_queue SET status = 'completed', completed_at = NOW() WHERE id = ?",
                            [$task['id']]
                        );
                    } catch (Exception $e) {
                        // 更新任务状态为失败
                        $db->query(
                            "UPDATE video_processing_queue SET status = 'failed', error_message = ? WHERE id = ?",
                            [$e->getMessage(), $task['id']]
                        );
                        throw $e;
                    }
                    break;
                    
                case 'transcode':
                    // 真正转码处理
                    require_once '../../includes/classes/VideoProcessor.php';
                    $videoProcessor = new VideoProcessor();
                    $videoProcessor->transcodeVideo($taskData['video_file_id']);
                    error_log("✅ 转码完成: 视频文件ID {$taskData['video_file_id']}");
                    break;
                    
                case 'segment':
                    // 真正切片处理
                    require_once '../../includes/classes/VideoProcessor.php';
                    $videoProcessor = new VideoProcessor();
                    $videoProcessor->segmentVideo($taskData['video_file_id']);
                    error_log("✅ 切片完成: 视频文件ID {$taskData['video_file_id']}");
                    break;
                    
                case 'asr':
                    // 真正语音识别处理
                    require_once '../../includes/classes/WhisperService.php';
                    $whisperService = new WhisperService();
                    
                    // 获取该视频文件的所有切片
                    $segments = $db->fetchAll(
                        "SELECT vs.* FROM video_segments vs 
                         WHERE vs.video_file_id = ? AND vs.status = 'completed'",
                        [$taskData['video_file_id']]
                    );
                    
                    foreach ($segments as $segment) {
                        $whisperService->processSegment($segment['id']);
                    }
                    error_log("✅ 语音识别完成: 视频文件ID {$taskData['video_file_id']}");
                    break;
                    
                case 'analysis':
                    // 真正视频分析处理
                    require_once '../../includes/classes/QwenOmniService.php';
                    $qwenOmniService = new QwenOmniService();
                    
                    // 获取该订单的所有切片
                    $segments = $db->fetchAll(
                        "SELECT vs.* FROM video_segments vs 
                         LEFT JOIN video_files vf ON vs.video_file_id = vf.id 
                         WHERE vf.order_id = ? AND vs.status = 'completed'",
                        [$task['order_id']]
                    );
                    
                    foreach ($segments as $segment) {
                        $qwenOmniService->analyzeSegment($segment['id']);
                    }
                    error_log("✅ 视频分析完成: 订单ID {$task['order_id']}");
                    break;
                    
                case 'report':
                    // 真正生成报告
                    require_once '../../includes/classes/VideoAnalysisEngine.php';
                    $videoAnalysisEngine = new VideoAnalysisEngine();
                    $videoAnalysisEngine->processVideoAnalysis($task['order_id']);
                    error_log("✅ 报告生成完成: 订单ID {$task['order_id']}");
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
