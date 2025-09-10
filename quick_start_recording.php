<?php
/**
 * 快速启动录制脚本
 * 确保录制能快速开始并完成
 */
require_once 'config/config.php';
require_once 'config/database.php';

class QuickStartRecorder {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 快速启动所有待录制的视频
     */
    public function quickStartAll() {
        try {
            $pdo = $this->db->getConnection();
            
            // 获取所有待录制的视频文件
            $videoFiles = $pdo->query(
                "SELECT vf.*, vao.title as order_title 
                 FROM video_files vf 
                 JOIN video_analysis_orders vao ON vf.order_id = vao.id 
                 WHERE vf.recording_status = 'pending' 
                 AND vf.flv_url IS NOT NULL 
                 AND vf.flv_url != ''
                 ORDER BY vf.created_at ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            
            $started = 0;
            
            foreach ($videoFiles as $videoFile) {
                try {
                    $this->quickStartRecording($videoFile);
                    $started++;
                } catch (Exception $e) {
                    error_log("快速启动录制失败 (ID: {$videoFile['id']}): " . $e->getMessage());
                }
            }
            
            return [
                'success' => true,
                'started' => $started,
                'message' => "快速启动了 {$started} 个录制任务"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 快速启动单个录制
     */
    private function quickStartRecording($videoFile) {
        $pdo = $this->db->getConnection();
        
        // 更新状态为录制中
        $pdo->exec("
            UPDATE video_files SET 
                recording_status = 'recording',
                recording_started_at = NOW(),
                recording_progress = 0
            WHERE id = {$videoFile['id']}
        ");
        
        // 记录开始日志
        $this->logProgress($videoFile['id'], 0, '快速启动录制...');
        
        // 获取录制配置
        $recordingDuration = $this->getSystemConfig('recording_duration', 60);
        $storagePath = $this->getSystemConfig('storage_path', '/storage/recordings');
        
        // 创建存储目录
        $orderDir = $storagePath . '/order_' . $videoFile['order_id'];
        if (!is_dir($orderDir)) {
            mkdir($orderDir, 0755, true);
        }
        
        // 生成输出文件路径
        $outputFile = $orderDir . '/video_' . $videoFile['id'] . '_' . time() . '.mp4';
        
        // 构建优化的FFmpeg命令
        $ffmpegPath = $this->getSystemConfig('ffmpeg_path', '/usr/bin/ffmpeg');
        $command = sprintf(
            '%s -i "%s" -t %d -c:v libx264 -preset ultrafast -tune zerolatency -c:a aac -b:a 64k -f mp4 "%s" 2>&1 &',
            $ffmpegPath,
            escapeshellarg($videoFile['flv_url']),
            $recordingDuration,
            escapeshellarg($outputFile)
        );
        
        error_log("执行快速录制命令: {$command}");
        
        // 执行录制命令
        exec($command);
        
        // 启动进度监控
        $this->startProgressMonitoring($videoFile['id'], $outputFile, $recordingDuration);
        
        error_log("快速启动录制: 视频文件ID {$videoFile['id']}, 输出文件: {$outputFile}");
    }
    
    /**
     * 启动进度监控
     */
    private function startProgressMonitoring($videoFileId, $outputFile, $totalDuration) {
        // 在后台启动进度监控
        $monitorScript = __DIR__ . '/recording_monitor.php';
        $command = "php {$monitorScript} {$videoFileId} " . escapeshellarg($outputFile) . " /dev/null {$totalDuration} > /dev/null 2>&1 &";
        
        exec($command);
    }
    
    /**
     * 记录进度日志
     */
    private function logProgress($videoFileId, $progress, $message) {
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO recording_progress_logs (video_file_id, progress, message, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$videoFileId, $progress, $message]);
        } catch (Exception $e) {
            error_log("记录进度日志失败: " . $e->getMessage());
        }
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
            return $defaultValue;
        }
    }
}

// 如果直接运行此脚本
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $recorder = new QuickStartRecorder();
    $result = $recorder->quickStartAll();
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
}
?>
