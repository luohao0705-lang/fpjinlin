<?php
/**
 * 实时录制器
 * 支持实时视频流显示和精确时间线控制
 */
require_once 'config/config.php';
require_once 'config/database.php';

class RealTimeRecorder {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 开始录制
     */
    public function startRecording($videoFileId) {
        try {
            $pdo = $this->db->getConnection();
            
            // 获取视频文件信息
            $videoFile = $pdo->query(
                "SELECT vf.*, vao.title as order_title 
                 FROM video_files vf 
                 JOIN video_analysis_orders vao ON vf.order_id = vao.id 
                 WHERE vf.id = {$videoFileId}"
            )->fetch(PDO::FETCH_ASSOC);
            
            if (!$videoFile) {
                throw new Exception('视频文件不存在');
            }
            
            if (!$videoFile['flv_url']) {
                throw new Exception('FLV地址未配置');
            }
            
            // 更新状态为录制中
            $pdo->exec("
                UPDATE video_files SET 
                    recording_status = 'recording',
                    recording_started_at = NOW(),
                    recording_progress = 0
                WHERE id = {$videoFileId}
            ");
            
            // 记录开始日志
            $this->logProgress($videoFileId, 0, '开始录制...');
            
            // 启动后台录制进程
            $this->startBackgroundRecording($videoFile);
            
            return [
                'success' => true,
                'message' => '录制已开始',
                'video_file_id' => $videoFileId,
                'flv_url' => $videoFile['flv_url']
            ];
            
        } catch (Exception $e) {
            error_log("开始录制失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 启动后台录制进程
     */
    private function startBackgroundRecording($videoFile) {
        // 创建存储目录
        $storagePath = $this->getSystemConfig('storage_path', '/storage/recordings');
        $orderDir = $storagePath . '/order_' . $videoFile['order_id'];
        if (!is_dir($orderDir)) {
            mkdir($orderDir, 0755, true);
        }
        
        // 生成输出文件路径
        $outputFile = $orderDir . '/video_' . $videoFile['id'] . '_' . time() . '.mp4';
        $pidFile = $orderDir . '/video_' . $videoFile['id'] . '.pid';
        
        // 获取录制配置
        $recordingDuration = $this->getSystemConfig('recording_duration', 60);
        
        // 构建FFmpeg命令 - 使用更高效的参数
        $ffmpegPath = $this->getSystemConfig('ffmpeg_path', '/usr/bin/ffmpeg');
        $command = sprintf(
            '%s -i "%s" -t %d -c:v libx264 -preset ultrafast -c:a aac -f mp4 "%s" > /dev/null 2>&1 & echo $! > "%s"',
            $ffmpegPath,
            escapeshellarg($videoFile['flv_url']),
            $recordingDuration,
            escapeshellarg($outputFile),
            escapeshellarg($pidFile)
        );
        
        // 执行命令
        exec($command);
        
        // 启动进度监控
        $this->startProgressMonitoring($videoFile['id'], $outputFile, $pidFile, $recordingDuration);
    }
    
    /**
     * 启动进度监控
     */
    private function startProgressMonitoring($videoFileId, $outputFile, $pidFile, $totalDuration) {
        // 在后台启动进度监控脚本
        $monitorScript = __DIR__ . '/recording_monitor.php';
        $command = "php {$monitorScript} {$videoFileId} " . escapeshellarg($outputFile) . " " . escapeshellarg($pidFile) . " {$totalDuration} > /dev/null 2>&1 &";
        
        exec($command);
    }
    
    /**
     * 停止录制
     */
    public function stopRecording($videoFileId) {
        try {
            $pdo = $this->db->getConnection();
            
            // 查找并终止FFmpeg进程
            $this->terminateRecordingProcess($videoFileId);
            
            // 更新状态为已停止
            $pdo->exec("
                UPDATE video_files SET 
                    recording_status = 'stopped',
                    recording_completed_at = NOW()
                WHERE id = {$videoFileId}
            ");
            
            $this->logProgress($videoFileId, 0, '录制已停止');
            
            return [
                'success' => true,
                'message' => '录制已停止'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取录制状态
     */
    public function getRecordingStatus($videoFileId) {
        try {
            $pdo = $this->db->getConnection();
            
            $videoFile = $pdo->query(
                "SELECT vf.*, 
                        CASE 
                            WHEN vf.video_type = 'self' THEN '本方视频'
                            WHEN vf.video_type = 'competitor' THEN CONCAT('同行视频', vf.video_index)
                            ELSE '未知类型'
                        END as display_name
                 FROM video_files vf 
                 WHERE vf.id = {$videoFileId}"
            )->fetch(PDO::FETCH_ASSOC);
            
            if (!$videoFile) {
                throw new Exception('视频文件不存在');
            }
            
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
            
            // 获取最新进度日志
            $latestProgress = $pdo->query(
                "SELECT * FROM recording_progress_logs 
                 WHERE video_file_id = {$videoFileId} 
                 ORDER BY created_at DESC 
                 LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            
            // 检查文件是否存在
            $fileExists = false;
            $fileSize = 0;
            if ($videoFile['file_path'] && file_exists($videoFile['file_path'])) {
                $fileExists = true;
                $fileSize = filesize($videoFile['file_path']);
            }
            
            return [
                'success' => true,
                'data' => [
                    'id' => $videoFile['id'],
                    'display_name' => $videoFile['display_name'],
                    'flv_url' => $videoFile['flv_url'],
                    'recording_status' => $videoFile['recording_status'],
                    'recording_progress' => intval($videoFile['recording_progress']),
                    'recording_duration' => $recordingDuration,
                    'recording_duration_formatted' => $this->formatDuration($recordingDuration),
                    'file_path' => $videoFile['file_path'],
                    'file_size' => $fileSize,
                    'file_size_formatted' => $this->formatBytes($fileSize),
                    'file_exists' => $fileExists,
                    'latest_progress' => $latestProgress,
                    'is_recording' => $videoFile['recording_status'] === 'recording',
                    'is_completed' => $videoFile['recording_status'] === 'completed',
                    'is_failed' => $videoFile['recording_status'] === 'failed',
                    'is_stopped' => $videoFile['recording_status'] === 'stopped'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 终止录制进程
     */
    private function terminateRecordingProcess($videoFileId) {
        try {
            $pdo = $this->db->getConnection();
            
            // 查找PID文件
            $videoFile = $pdo->query("SELECT * FROM video_files WHERE id = {$videoFileId}")->fetch(PDO::FETCH_ASSOC);
            if ($videoFile && $videoFile['file_path']) {
                $orderDir = dirname($videoFile['file_path']);
                $pidFile = $orderDir . '/video_' . $videoFileId . '.pid';
                
                if (file_exists($pidFile)) {
                    $pid = trim(file_get_contents($pidFile));
                    if ($pid && is_numeric($pid)) {
                        exec("kill -TERM {$pid} 2>/dev/null");
                        unlink($pidFile);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("终止录制进程失败: " . $e->getMessage());
        }
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
    
    /**
     * 格式化时长
     */
    private function formatDuration($seconds) {
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

// API接口
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $recorder = new RealTimeRecorder();
    $action = $_GET['action'];
    
    switch ($action) {
        case 'start':
            $videoFileId = intval($_POST['video_file_id'] ?? 0);
            echo json_encode($recorder->startRecording($videoFileId));
            break;
            
        case 'stop':
            $videoFileId = intval($_POST['video_file_id'] ?? 0);
            echo json_encode($recorder->stopRecording($videoFileId));
            break;
            
        case 'status':
            $videoFileId = intval($_GET['video_file_id'] ?? 0);
            echo json_encode($recorder->getRecordingStatus($videoFileId));
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
}
?>
