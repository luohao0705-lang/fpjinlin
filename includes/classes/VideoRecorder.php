<?php
/**
 * 视频录制服务
 * 负责录制FLV直播流
 */

class VideoRecorder {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = new SystemConfig();
    }
    
    /**
     * 开始录制视频
     */
    public function startRecording($videoFileId, $flvUrl, $duration = 60) {
        try {
            $pdo = $this->db->getConnection();
            
            // 获取视频文件信息
            $videoFile = $this->getVideoFile($videoFileId);
            if (!$videoFile) {
                throw new Exception("视频文件不存在: {$videoFileId}");
            }
            
            // 生成输出文件路径
            $outputPath = $this->generateOutputPath($videoFileId);
            
            // 更新视频文件状态
            $this->updateVideoFileStatus($videoFileId, 'recording', 0, '开始录制...');
            
            // 构建FFmpeg命令
            $command = $this->buildRecordingCommand($flvUrl, $outputPath, $duration);
            
            // 异步执行录制命令
            $this->executeRecordingAsync($videoFileId, $command, $outputPath);
            
            return [
                'success' => true,
                'message' => '录制已开始',
                'output_path' => $outputPath
            ];
            
        } catch (Exception $e) {
            error_log("开始录制失败: " . $e->getMessage());
            $this->updateVideoFileStatus($videoFileId, 'failed', 0, $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取录制进度
     */
    public function getRecordingProgress($videoFileId) {
        try {
            $pdo = $this->db->getConnection();
            
            // 获取视频文件信息
            $videoFile = $this->getVideoFile($videoFileId);
            if (!$videoFile) {
                return ['completed' => false, 'progress' => 0, 'message' => '视频文件不存在'];
            }
            
            // 检查录制状态
            if ($videoFile['status'] === 'recording') {
                // 检查录制进程是否还在运行
                $pid = $this->getRecordingPid($videoFileId);
                if (!$pid || !$this->isProcessRunning($pid)) {
                    // 进程已结束，检查输出文件
                    $outputPath = $this->generateOutputPath($videoFileId);
                    if (file_exists($outputPath) && filesize($outputPath) > 0) {
                        // 录制完成
                        $this->updateVideoFileStatus($videoFileId, 'recording_completed', 100, '录制完成');
                        return ['completed' => true, 'progress' => 100, 'message' => '录制完成'];
                    } else {
                        // 录制失败
                        $this->updateVideoFileStatus($videoFileId, 'failed', 0, '录制失败：输出文件不存在');
                        return ['completed' => false, 'progress' => 0, 'message' => '录制失败'];
                    }
                } else {
                    // 进程还在运行，估算进度
                    $progress = $this->estimateRecordingProgress($videoFileId, $videoFile['recording_started_at']);
                    return ['completed' => false, 'progress' => $progress, 'message' => '录制中...'];
                }
            } elseif ($videoFile['status'] === 'recording_completed') {
                return ['completed' => true, 'progress' => 100, 'message' => '录制完成'];
            } elseif ($videoFile['status'] === 'failed') {
                return ['completed' => false, 'progress' => 0, 'message' => '录制失败'];
            }
            
            return ['completed' => false, 'progress' => 0, 'message' => '未知状态'];
            
        } catch (Exception $e) {
            error_log("获取录制进度失败: " . $e->getMessage());
            return ['completed' => false, 'progress' => 0, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 验证录制结果
     */
    public function validateRecording($videoFileId) {
        try {
            $outputPath = $this->generateOutputPath($videoFileId);
            
            if (!file_exists($outputPath)) {
                return ['valid' => false, 'message' => '输出文件不存在'];
            }
            
            $fileSize = filesize($outputPath);
            if ($fileSize < 1024) { // 小于1KB认为无效
                return ['valid' => false, 'message' => '文件太小，可能录制失败'];
            }
            
            // 使用FFprobe检查视频信息
            $videoInfo = $this->getVideoInfo($outputPath);
            if (!$videoInfo) {
                return ['valid' => false, 'message' => '无法获取视频信息'];
            }
            
            // 更新视频文件信息
            $this->updateVideoFileInfo($videoFileId, $fileSize, $videoInfo['duration'], $videoInfo['resolution']);
            
            return [
                'valid' => true,
                'message' => '录制验证成功',
                'file_size' => $fileSize,
                'duration' => $videoInfo['duration'],
                'resolution' => $videoInfo['resolution']
            ];
            
        } catch (Exception $e) {
            error_log("验证录制结果失败: " . $e->getMessage());
            return ['valid' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 构建录制命令
     */
    private function buildRecordingCommand($flvUrl, $outputPath, $duration) {
        $ffmpegPath = $this->config->get('ffmpeg_path', '/usr/bin/ffmpeg');
        $timeout = $duration + 10; // 给一些缓冲时间
        
        $command = sprintf(
            '%s -i "%s" -t %d -c copy -avoid_negative_ts make_zero -f mp4 "%s" 2>&1',
            $ffmpegPath,
            $flvUrl,
            $duration,
            $outputPath
        );
        
        return $command;
    }
    
    /**
     * 异步执行录制命令
     */
    private function executeRecordingAsync($videoFileId, $command, $outputPath) {
        // 创建输出目录
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // 记录开始时间
        $this->updateVideoFileStatus($videoFileId, 'recording', 0, '开始录制...');
        $this->setRecordingStartTime($videoFileId);
        
        // 异步执行命令
        $logFile = $this->getLogPath($videoFileId);
        $pidFile = $this->getPidPath($videoFileId);
        
        $fullCommand = sprintf(
            'nohup %s > %s 2>&1 & echo $! > %s',
            $command,
            $logFile,
            $pidFile
        );
        
        exec($fullCommand);
        
        // 记录PID
        $this->setRecordingPid($videoFileId, $this->getPidFromFile($pidFile));
    }
    
    /**
     * 估算录制进度
     */
    private function estimateRecordingProgress($videoFileId, $startTime) {
        if (!$startTime) {
            return 0;
        }
        
        $elapsed = time() - strtotime($startTime);
        $duration = $this->config->get('recording_duration', 60);
        
        $progress = min(($elapsed / $duration) * 100, 95); // 最多95%，等实际完成
        return round($progress);
    }
    
    /**
     * 获取视频信息
     */
    private function getVideoInfo($filePath) {
        $ffprobePath = $this->config->get('ffprobe_path', '/usr/bin/ffprobe');
        
        $command = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams "%s"',
            $ffprobePath,
            $filePath
        );
        
        $output = shell_exec($command);
        $info = json_decode($output, true);
        
        if (!$info || !isset($info['streams'][0])) {
            return null;
        }
        
        $stream = $info['streams'][0];
        $format = $info['format'];
        
        return [
            'duration' => round($format['duration'] ?? 0),
            'resolution' => ($stream['width'] ?? 0) . 'x' . ($stream['height'] ?? 0),
            'bitrate' => $format['bit_rate'] ?? 0,
            'codec' => $stream['codec_name'] ?? 'unknown'
        ];
    }
    
    /**
     * 获取视频文件信息
     */
    private function getVideoFile($videoFileId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM video_files WHERE id = ?");
        $stmt->execute([$videoFileId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 更新视频文件状态
     */
    private function updateVideoFileStatus($videoFileId, $status, $progress, $message) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            UPDATE video_files 
            SET status = ?, stage_progress = ?, error_message = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $progress, $message, $videoFileId]);
    }
    
    /**
     * 设置录制开始时间
     */
    private function setRecordingStartTime($videoFileId) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            UPDATE video_files 
            SET recording_started_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$videoFileId]);
    }
    
    /**
     * 更新视频文件信息
     */
    private function updateVideoFileInfo($videoFileId, $fileSize, $duration, $resolution) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            UPDATE video_files 
            SET file_size = ?, duration = ?, resolution = ?, recording_completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$fileSize, $duration, $resolution, $videoFileId]);
    }
    
    /**
     * 生成输出文件路径
     */
    private function generateOutputPath($videoFileId) {
        $storageDir = $this->config->get('storage_path', '/storage/recordings');
        return "{$storageDir}/video_{$videoFileId}.mp4";
    }
    
    /**
     * 获取日志文件路径
     */
    private function getLogPath($videoFileId) {
        $logDir = $this->config->get('log_path', '/storage/logs');
        return "{$logDir}/recording_{$videoFileId}.log";
    }
    
    /**
     * 获取PID文件路径
     */
    private function getPidPath($videoFileId) {
        $pidDir = $this->config->get('pid_path', '/storage/pids');
        if (!is_dir($pidDir)) {
            mkdir($pidDir, 0755, true);
        }
        return "{$pidDir}/recording_{$videoFileId}.pid";
    }
    
    /**
     * 设置录制PID
     */
    private function setRecordingPid($videoFileId, $pid) {
        $pidFile = $this->getPidPath($videoFileId);
        file_put_contents($pidFile, $pid);
    }
    
    /**
     * 获取录制PID
     */
    private function getRecordingPid($videoFileId) {
        $pidFile = $this->getPidPath($videoFileId);
        if (file_exists($pidFile)) {
            return trim(file_get_contents($pidFile));
        }
        return null;
    }
    
    /**
     * 从文件获取PID
     */
    private function getPidFromFile($pidFile) {
        if (file_exists($pidFile)) {
            return trim(file_get_contents($pidFile));
        }
        return null;
    }
    
    /**
     * 检查进程是否在运行
     */
    private function isProcessRunning($pid) {
        if (!$pid) {
            return false;
        }
        
        $result = shell_exec("ps -p {$pid} -o pid= 2>/dev/null");
        return !empty(trim($result));
    }
}
?>
