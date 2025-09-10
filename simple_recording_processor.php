<?php
/**
 * 简化录制处理器
 * 确保录制在2分钟内完成
 */
require_once 'config/config.php';
require_once 'config/database.php';

class SimpleRecordingProcessor {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 处理录制任务
     */
    public function processRecordingTasks() {
        try {
            $pdo = $this->db->getConnection();
            
            // 获取待录制的视频文件
            $videoFiles = $pdo->query(
                "SELECT vf.*, vao.title as order_title 
                 FROM video_files vf 
                 JOIN video_analysis_orders vao ON vf.order_id = vao.id 
                 WHERE vf.recording_status = 'pending' 
                 AND vf.flv_url IS NOT NULL 
                 AND vf.flv_url != ''
                 ORDER BY vf.created_at ASC 
                 LIMIT 3"
            )->fetchAll(PDO::FETCH_ASSOC);
            
            $processed = 0;
            
            foreach ($videoFiles as $videoFile) {
                try {
                    $this->recordVideo($videoFile);
                    $processed++;
                } catch (Exception $e) {
                    error_log("录制视频失败 (ID: {$videoFile['id']}): " . $e->getMessage());
                    $this->markRecordingFailed($videoFile['id'], $e->getMessage());
                }
            }
            
            return [
                'success' => true,
                'processed' => $processed,
                'message' => "处理了 {$processed} 个录制任务"
            ];
            
        } catch (Exception $e) {
            error_log("处理录制任务失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 录制单个视频
     */
    private function recordVideo($videoFile) {
        $pdo = $this->db->getConnection();
        
        // 更新状态为录制中
        $pdo->exec("UPDATE video_files SET recording_status = 'recording', recording_started_at = NOW() WHERE id = {$videoFile['id']}");
        
        // 记录开始日志
        $this->logProgress($videoFile['id'], 0, '开始录制视频...');
        
        // 获取录制配置
        $recordingDuration = $this->getSystemConfig('recording_duration', 60); // 默认60秒
        $segmentDuration = $this->getSystemConfig('segment_duration', 20); // 默认20秒
        
        // 创建存储目录
        $storagePath = $this->getSystemConfig('storage_path', '/storage/recordings');
        $orderDir = $storagePath . '/order_' . $videoFile['order_id'];
        if (!is_dir($orderDir)) {
            mkdir($orderDir, 0755, true);
        }
        
        // 生成输出文件名
        $outputFile = $orderDir . '/video_' . $videoFile['id'] . '_' . time() . '.mp4';
        
        // 构建FFmpeg命令
        $ffmpegPath = $this->getSystemConfig('ffmpeg_path', '/usr/bin/ffmpeg');
        $command = sprintf(
            '%s -i "%s" -t %d -c copy -f mp4 "%s" 2>&1',
            $ffmpegPath,
            escapeshellarg($videoFile['flv_url']),
            $recordingDuration,
            escapeshellarg($outputFile)
        );
        
        error_log("执行录制命令: {$command}");
        
        // 执行录制命令
        $startTime = time();
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        $endTime = time();
        $actualDuration = $endTime - $startTime;
        
        if ($returnCode === 0 && file_exists($outputFile)) {
            // 录制成功
            $fileSize = filesize($outputFile);
            
            // 更新视频文件记录
            $pdo->exec("
                UPDATE video_files SET 
                    recording_status = 'completed',
                    recording_completed_at = NOW(),
                    file_path = '{$outputFile}',
                    file_size = {$fileSize},
                    duration = {$recordingDuration},
                    recording_progress = 100
                WHERE id = {$videoFile['id']}
            ");
            
            // 记录完成日志
            $this->logProgress($videoFile['id'], 100, "录制完成，文件大小: " . $this->formatBytes($fileSize));
            
            error_log("录制成功: 视频文件ID {$videoFile['id']}, 文件: {$outputFile}, 大小: {$fileSize} 字节");
            
        } else {
            // 录制失败
            $errorMsg = "FFmpeg执行失败 (返回码: {$returnCode}): " . implode("\n", $output);
            throw new Exception($errorMsg);
        }
    }
    
    /**
     * 标记录制失败
     */
    private function markRecordingFailed($videoFileId, $errorMessage) {
        $pdo = $this->db->getConnection();
        
        $pdo->exec("
            UPDATE video_files SET 
                recording_status = 'failed',
                error_message = '{$errorMessage}',
                recording_completed_at = NOW()
            WHERE id = {$videoFileId}
        ");
        
        $this->logProgress($videoFileId, 0, "录制失败: {$errorMessage}");
    }
    
    /**
     * 记录进度日志
     */
    private function logProgress($videoFileId, $progress, $message) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO recording_progress_logs (video_file_id, progress, message, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$videoFileId, $progress, $message]);
    }
    
    /**
     * 获取系统配置
     */
    private function getSystemConfig($key, $defaultValue = null) {
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("SELECT config_value FROM system_configs WHERE config_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['config_value'] : $defaultValue;
        } catch (Exception $e) {
            error_log("获取系统配置失败: {$key} - " . $e->getMessage());
            return $defaultValue;
        }
    }
    
    /**
     * 格式化文件大小
     */
    private function formatBytes($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}

// 如果直接运行此脚本
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $processor = new SimpleRecordingProcessor();
    $result = $processor->processRecordingTasks();
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
}
?>
