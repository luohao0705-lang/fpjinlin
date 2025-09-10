<?php
/**
 * 录制任务定时处理器
 * 每30秒执行一次，确保录制任务及时处理
 */
require_once 'simple_recording_processor.php';

// 设置执行时间限制
set_time_limit(300); // 5分钟

// 记录开始时间
$startTime = microtime(true);
$logFile = '/tmp/recording_processor.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

try {
    writeLog("开始执行录制任务处理器");
    
    $processor = new SimpleRecordingProcessor();
    $result = $processor->processRecordingTasks();
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    if ($result['success']) {
        writeLog("录制任务处理完成: {$result['message']}, 执行时间: {$executionTime}秒");
    } else {
        writeLog("录制任务处理失败: {$result['message']}, 执行时间: {$executionTime}秒");
    }
    
    // 输出结果（用于命令行查看）
    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'],
        'execution_time' => $executionTime,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    $errorMsg = "录制任务处理器异常: " . $e->getMessage();
    writeLog($errorMsg);
    
    echo json_encode([
        'success' => false,
        'message' => $errorMsg,
        'execution_time' => $executionTime,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
