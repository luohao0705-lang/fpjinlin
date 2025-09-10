<?php
/**
 * 定时任务处理器
 * 用于处理视频分析工作流的各个阶段
 * 建议每30秒执行一次
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/VideoAnalysisWorkflow.php';
require_once 'task_processor_workflow.php';

// 设置脚本执行时间限制
set_time_limit(300); // 5分钟

// 设置内存限制
ini_set('memory_limit', '512M');

// 启用错误日志
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('display_errors', 0);

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "[" . date('Y-m-d H:i:s') . "] 开始处理工作流任务...\n";
    
    // 1. 处理录制阶段
    echo "处理录制阶段...\n";
    $recordingOrders = $pdo->query("
        SELECT id FROM video_analysis_orders 
        WHERE status IN ('recording', 'recording_completed') 
        ORDER BY created_at ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($recordingOrders as $orderId) {
        $workflow = new VideoAnalysisWorkflow();
        $result = $workflow->processRecording($orderId);
        
        if ($result['success']) {
            echo "订单 {$orderId} 录制处理完成，进度: {$result['progress']}%\n";
        } else {
            echo "订单 {$orderId} 录制处理失败: {$result['message']}\n";
        }
    }
    
    // 2. 处理转码阶段
    echo "处理转码阶段...\n";
    $transcodingOrders = $pdo->query("
        SELECT id FROM video_analysis_orders 
        WHERE status IN ('transcoding', 'transcoding_completed') 
        ORDER BY created_at ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($transcodingOrders as $orderId) {
        $workflow = new VideoAnalysisWorkflow();
        $result = $workflow->processTranscoding($orderId);
        
        if ($result['success']) {
            echo "订单 {$orderId} 转码处理完成，进度: {$result['progress']}%\n";
        } else {
            echo "订单 {$orderId} 转码处理失败: {$result['message']}\n";
        }
    }
    
    // 3. 处理AI分析阶段
    echo "处理AI分析阶段...\n";
    $aiAnalyzingOrders = $pdo->query("
        SELECT id FROM video_analysis_orders 
        WHERE status IN ('ai_analyzing', 'ai_analysis_completed') 
        ORDER BY created_at ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($aiAnalyzingOrders as $orderId) {
        $workflow = new VideoAnalysisWorkflow();
        $result = $workflow->processAIAnalysis($orderId);
        
        if ($result['success']) {
            echo "订单 {$orderId} AI分析处理完成，进度: {$result['progress']}%\n";
        } else {
            echo "订单 {$orderId} AI分析处理失败: {$result['message']}\n";
        }
    }
    
    // 4. 处理语音提取阶段
    echo "处理语音提取阶段...\n";
    $speechExtractingOrders = $pdo->query("
        SELECT id FROM video_analysis_orders 
        WHERE status IN ('speech_extracting', 'speech_extraction_completed') 
        ORDER BY created_at ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($speechExtractingOrders as $orderId) {
        $workflow = new VideoAnalysisWorkflow();
        $result = $workflow->processSpeechExtraction($orderId);
        
        if ($result['success']) {
            echo "订单 {$orderId} 语音提取处理完成，进度: {$result['progress']}%\n";
        } else {
            echo "订单 {$orderId} 语音提取处理失败: {$result['message']}\n";
        }
    }
    
    // 5. 处理话术分析阶段
    echo "处理话术分析阶段...\n";
    $scriptAnalyzingOrders = $pdo->query("
        SELECT id FROM video_analysis_orders 
        WHERE status IN ('script_analyzing', 'script_analysis_completed') 
        ORDER BY created_at ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($scriptAnalyzingOrders as $orderId) {
        $workflow = new VideoAnalysisWorkflow();
        $result = $workflow->processScriptAnalysis($orderId);
        
        if ($result['success']) {
            echo "订单 {$orderId} 话术分析处理完成，进度: {$result['progress']}%\n";
        } else {
            echo "订单 {$orderId} 话术分析处理失败: {$result['message']}\n";
        }
    }
    
    // 6. 处理报告生成阶段
    echo "处理报告生成阶段...\n";
    $reportGeneratingOrders = $pdo->query("
        SELECT id FROM video_analysis_orders 
        WHERE status = 'report_generating' 
        ORDER BY created_at ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($reportGeneratingOrders as $orderId) {
        $workflow = new VideoAnalysisWorkflow();
        $result = $workflow->processReportGeneration($orderId);
        
        if ($result['success']) {
            echo "订单 {$orderId} 报告生成处理完成\n";
        } else {
            echo "订单 {$orderId} 报告生成处理失败: {$result['message']}\n";
        }
    }
    
    // 7. 处理队列任务
    echo "处理队列任务...\n";
    $taskProcessor = new TaskProcessorWorkflow();
    $result = $taskProcessor->processPendingTasks();
    
    if ($result['success']) {
        echo "队列任务处理完成，处理了 {$result['processed_count']} 个任务\n";
    } else {
        echo "队列任务处理失败: {$result['message']}\n";
    }
    
    // 8. 清理过期数据
    echo "清理过期数据...\n";
    $this->cleanupExpiredData();
    
    echo "[" . date('Y-m-d H:i:s') . "] 工作流任务处理完成\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] 处理失败: " . $e->getMessage() . "\n";
    error_log("工作流处理器错误: " . $e->getMessage());
}

/**
 * 清理过期数据
 */
function cleanupExpiredData() {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // 清理过期的错误日志（保留7天）
        $stmt = $pdo->prepare("DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $deletedLogs = $stmt->rowCount();
        
        // 清理过期的短信验证码
        $stmt = $pdo->prepare("DELETE FROM sms_codes WHERE expires_at < NOW()");
        $stmt->execute();
        $deletedSms = $stmt->rowCount();
        
        // 清理过期的录制进度日志（保留3天）
        $stmt = $pdo->prepare("DELETE FROM recording_progress_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");
        $stmt->execute();
        $deletedProgress = $stmt->rowCount();
        
        // 清理过期的临时文件
        $tempDir = '/storage/temp';
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            $deletedFiles = 0;
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < time() - 3600) { // 1小时前
                    unlink($file);
                    $deletedFiles++;
                }
            }
        }
        
        echo "清理完成: 错误日志 {$deletedLogs} 条, 短信验证码 {$deletedSms} 条, 进度日志 {$deletedProgress} 条, 临时文件 {$deletedFiles} 个\n";
        
    } catch (Exception $e) {
        echo "清理过期数据失败: " . $e->getMessage() . "\n";
    }
}
?>
