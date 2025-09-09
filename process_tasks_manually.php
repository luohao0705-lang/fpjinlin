<?php
/**
 * 手动处理视频分析任务
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
require_once 'config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$orderId = intval($_GET['order_id'] ?? 24);

echo "<h2>手动处理视频分析任务 - 订单ID: {$orderId}</h2>";

try {
    $db = new Database();
    
    // 获取待处理的任务
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? AND status = 'pending' 
         ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    echo "<h3>找到 " . count($tasks) . " 个待处理任务</h3>";
    
    if (empty($tasks)) {
        echo "没有待处理的任务<br>";
        exit;
    }
    
    // 处理第一个任务
    $firstTask = $tasks[0];
    echo "<h3>处理第一个任务: {$firstTask['task_type']}</h3>";
    
    // 更新任务状态为处理中
    $db->query(
        "UPDATE video_processing_queue SET status = 'processing', started_at = NOW() WHERE id = ?",
        [$firstTask['id']]
    );
    echo "✅ 任务状态已更新为处理中<br>";
    
    $taskData = json_decode($firstTask['task_data'], true);
    echo "任务数据: " . json_encode($taskData) . "<br>";
    
    // 根据任务类型进行处理
    switch ($firstTask['task_type']) {
        case 'record':
            echo "<h4>执行录制任务</h4>";
            require_once 'includes/classes/VideoProcessor.php';
            $videoProcessor = new VideoProcessor();
            
            $videoFile = $db->fetchOne("SELECT * FROM video_files WHERE id = ?", [$taskData['video_file_id']]);
            if (!$videoFile) {
                throw new Exception("视频文件不存在: " . $taskData['video_file_id']);
            }
            
            echo "开始录制视频文件: {$videoFile['id']}<br>";
            echo "FLV地址: {$videoFile['flv_url']}<br>";
            
            $videoProcessor->recordVideo($videoFile['id'], $videoFile['flv_url']);
            echo "✅ 录制任务完成<br>";
            break;
            
        case 'transcode':
            echo "<h4>执行转码任务</h4>";
            require_once 'includes/classes/VideoProcessor.php';
            $videoProcessor = new VideoProcessor();
            $videoProcessor->transcodeVideo($taskData['video_file_id']);
            echo "✅ 转码任务完成<br>";
            break;
            
        case 'segment':
            echo "<h4>执行切片任务</h4>";
            require_once 'includes/classes/VideoProcessor.php';
            $videoProcessor = new VideoProcessor();
            $videoProcessor->segmentVideo($taskData['video_file_id']);
            echo "✅ 切片任务完成<br>";
            break;
            
        case 'asr':
            echo "<h4>执行ASR任务</h4>";
            require_once 'includes/classes/WhisperService.php';
            $whisperService = new WhisperService();
            
            $segments = $db->fetchAll(
                "SELECT vs.* FROM video_segments vs 
                 WHERE vs.video_file_id = ? AND vs.status = 'completed'",
                [$taskData['video_file_id']]
            );
            
            foreach ($segments as $segment) {
                $whisperService->processSegment($segment['id']);
            }
            echo "✅ ASR任务完成<br>";
            break;
            
        case 'analysis':
            echo "<h4>执行分析任务</h4>";
            require_once 'includes/classes/QwenOmniService.php';
            $qwenOmniService = new QwenOmniService();
            
            $segments = $db->fetchAll(
                "SELECT vs.* FROM video_segments vs 
                 LEFT JOIN video_files vf ON vs.video_file_id = vf.id 
                 WHERE vf.order_id = ? AND vs.status = 'completed'",
                [$orderId]
            );
            
            foreach ($segments as $segment) {
                $qwenOmniService->analyzeSegment($segment['id']);
            }
            echo "✅ 分析任务完成<br>";
            break;
            
        case 'report':
            echo "<h4>执行报告任务</h4>";
            require_once 'includes/classes/VideoAnalysisEngine.php';
            $videoAnalysisEngine = new VideoAnalysisEngine();
            $videoAnalysisEngine->processVideoAnalysis($orderId);
            echo "✅ 报告任务完成<br>";
            break;
            
        default:
            throw new Exception("未知任务类型: " . $firstTask['task_type']);
    }
    
    // 更新任务状态为完成
    $db->query(
        "UPDATE video_processing_queue SET status = 'completed', completed_at = NOW() WHERE id = ?",
        [$firstTask['id']]
    );
    echo "✅ 任务状态已更新为完成<br>";
    
    echo "<h3>✅ 任务处理完成</h3>";
    
} catch (Exception $e) {
    echo "<h3>❌ 任务处理失败</h3>";
    echo "错误: " . $e->getMessage() . "<br>";
    echo "文件: " . $e->getFile() . "<br>";
    echo "行号: " . $e->getLine() . "<br>";
    echo "堆栈: <pre>" . $e->getTraceAsString() . "</pre>";
    
    // 更新任务状态为失败
    if (isset($firstTask)) {
        $db->query(
            "UPDATE video_processing_queue SET status = 'failed', error_message = ? WHERE id = ?",
            [$e->getMessage(), $firstTask['id']]
        );
    }
} catch (Error $e) {
    echo "<h3>❌ 任务处理失败(致命错误)</h3>";
    echo "错误: " . $e->getMessage() . "<br>";
    echo "文件: " . $e->getFile() . "<br>";
    echo "行号: " . $e->getLine() . "<br>";
    echo "堆栈: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
