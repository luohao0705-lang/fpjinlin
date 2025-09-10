<?php
/**
 * 录制进度监控脚本
 * 监控录制进度并实时更新数据库
 */
require_once 'config/config.php';
require_once 'config/database.php';

if ($argc < 5) {
    die("参数不足\n");
}

$videoFileId = intval($argv[1]);
$outputFile = $argv[2];
$pidFile = $argv[3];
$totalDuration = intval($argv[4]);

$db = new Database();
$pdo = $db->getConnection();

function logProgress($videoFileId, $progress, $message) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO recording_progress_logs (video_file_id, progress, message, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$videoFileId, $progress, $message]);
    } catch (Exception $e) {
        error_log("记录进度日志失败: " . $e->getMessage());
    }
}

function updateProgress($videoFileId, $progress, $fileSize = 0) {
    global $pdo;
    try {
        $pdo->exec("
            UPDATE video_files SET 
                recording_progress = {$progress}
                " . ($fileSize > 0 ? ", file_size = {$fileSize}" : "") . "
            WHERE id = {$videoFileId}
        ");
    } catch (Exception $e) {
        error_log("更新进度失败: " . $e->getMessage());
    }
}

try {
    logProgress($videoFileId, 0, '开始监控录制进度...');
    
    $startTime = time();
    $lastProgress = 0;
    
    // 监控录制进度
    while (true) {
        // 检查PID文件是否存在（进程是否还在运行）
        if (!file_exists($pidFile)) {
            break;
        }
        
        // 检查输出文件是否存在
        if (file_exists($outputFile)) {
            $fileSize = filesize($outputFile);
            $currentTime = time();
            $elapsed = $currentTime - $startTime;
            
            // 计算进度百分比
            $progress = min(100, intval(($elapsed / $totalDuration) * 100));
            
            // 每5秒更新一次进度
            if ($progress - $lastProgress >= 5) {
                updateProgress($videoFileId, $progress, $fileSize);
                logProgress($videoFileId, $progress, "录制进度: {$progress}%, 已录制: {$elapsed}秒, 文件大小: " . formatBytes($fileSize));
                $lastProgress = $progress;
            }
        }
        
        // 检查是否超时（总时长 + 30秒缓冲）
        if ((time() - $startTime) > ($totalDuration + 30)) {
            logProgress($videoFileId, 0, '录制超时，强制结束');
            break;
        }
        
        sleep(1); // 每秒检查一次
    }
    
    // 录制完成或结束
    if (file_exists($outputFile)) {
        $fileSize = filesize($outputFile);
        $finalDuration = time() - $startTime;
        
        // 更新最终状态
        $pdo->exec("
            UPDATE video_files SET 
                recording_status = 'completed',
                recording_completed_at = NOW(),
                file_path = '{$outputFile}',
                file_size = {$fileSize},
                duration = {$totalDuration},
                recording_progress = 100
            WHERE id = {$videoFileId}
        ");
        
        logProgress($videoFileId, 100, "录制完成，总时长: {$finalDuration}秒，文件大小: " . formatBytes($fileSize));
    } else {
        // 录制失败
        $pdo->exec("
            UPDATE video_files SET 
                recording_status = 'failed',
                recording_completed_at = NOW(),
                error_message = '录制文件未生成'
            WHERE id = {$videoFileId}
        ");
        
        logProgress($videoFileId, 0, '录制失败：文件未生成');
    }
    
    // 清理PID文件
    if (file_exists($pidFile)) {
        unlink($pidFile);
    }
    
} catch (Exception $e) {
    // 录制异常
    $pdo->exec("
        UPDATE video_files SET 
            recording_status = 'failed',
            recording_completed_at = NOW(),
            error_message = '{$e->getMessage()}'
        WHERE id = {$videoFileId}
    ");
    
    logProgress($videoFileId, 0, '录制异常: ' . $e->getMessage());
}

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
