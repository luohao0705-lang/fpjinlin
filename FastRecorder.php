<?php
/**
 * 快速录制器
 * 专注于录制视频，不处理其他复杂逻辑
 */

class FastRecorder {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * 快速录制视频
     */
    public function recordVideo($orderId, $flvUrl, $maxDuration = 3600) {
        echo "🎬 快速录制器启动\n";
        echo "订单ID: $orderId\n";
        echo "FLV地址: $flvUrl\n";
        echo "最大时长: {$maxDuration}秒\n";
        echo "==================\n\n";
        
        try {
            // 1. 验证FLV地址
            $this->validateFlvUrl($flvUrl);
            
            // 2. 创建录制目录
            $recordingDir = "/tmp/fast_recording_$orderId";
            $this->createRecordingDir($recordingDir);
            
            // 3. 开始录制
            $outputFile = "$recordingDir/video.mp4";
            $this->startRecording($flvUrl, $outputFile, $maxDuration);
            
            // 4. 检查录制结果
            if (!file_exists($outputFile) || filesize($outputFile) < 1024) {
                throw new Exception("录制失败：文件不存在或文件过小");
            }
            
            // 5. 获取视频信息
            $fileSize = filesize($outputFile);
            $duration = $this->getVideoDuration($outputFile);
            
            echo "✅ 录制成功！\n";
            echo "文件路径: $outputFile\n";
            echo "文件大小: " . $this->formatBytes($fileSize) . "\n";
            echo "视频时长: {$duration}秒\n";
            
            // 6. 保存到数据库
            $this->saveRecordingResult($orderId, $outputFile, $fileSize, $duration);
            
            return [
                'success' => true,
                'file_path' => $outputFile,
                'file_size' => $fileSize,
                'duration' => $duration
            ];
            
        } catch (Exception $e) {
            echo "❌ 录制失败: " . $e->getMessage() . "\n";
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 验证FLV地址
     */
    private function validateFlvUrl($flvUrl) {
        if (!filter_var($flvUrl, FILTER_VALIDATE_URL)) {
            throw new Exception('FLV地址格式无效');
        }
        
        // 检查地址是否过期
        if (preg_match('/expire=(\d+)/', $flvUrl, $matches)) {
            $expireTime = intval($matches[1]);
            $currentTime = time();
            
            if ($expireTime < $currentTime) {
                throw new Exception('FLV地址已过期，请重新获取');
            }
            
            $remainingTime = $expireTime - $currentTime;
            if ($remainingTime < 300) { // 少于5分钟
                echo "⚠️ 警告：FLV地址将在{$remainingTime}秒后过期\n";
            }
        }
    }
    
    /**
     * 创建录制目录
     */
    private function createRecordingDir($dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new Exception("无法创建录制目录: $dir");
            }
        }
        
        if (!is_writable($dir)) {
            throw new Exception("录制目录不可写: $dir");
        }
    }
    
    /**
     * 开始录制
     */
    private function startRecording($flvUrl, $outputFile, $maxDuration) {
        echo "📹 开始录制...\n";
        
        // 构建FFmpeg命令
        $command = sprintf(
            'ffmpeg -i %s -t %d -c copy -avoid_negative_ts make_zero -fflags +genpts %s -y 2>&1',
            escapeshellarg($flvUrl),
            $maxDuration,
            escapeshellarg($outputFile)
        );
        
        echo "执行命令: $command\n";
        
        // 执行录制
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            throw new Exception("FFmpeg录制失败 (返回码: $returnCode): $errorMsg");
        }
        
        echo "✅ FFmpeg录制完成\n";
    }
    
    /**
     * 获取视频时长
     */
    private function getVideoDuration($filePath) {
        $command = "ffprobe -v quiet -show_entries format=duration -of csv=p=0 " . escapeshellarg($filePath);
        $output = [];
        exec($command, $output);
        return intval($output[0] ?? 0);
    }
    
    /**
     * 保存录制结果
     */
    private function saveRecordingResult($orderId, $filePath, $fileSize, $duration) {
        // 更新订单状态
        $this->db->query(
            "UPDATE video_analysis_orders SET status = 'completed', completed_at = NOW() WHERE id = ?",
            [$orderId]
        );
        
        // 保存视频文件记录
        $this->db->insert(
            "INSERT INTO video_files (order_id, video_type, video_index, original_url, flv_url, file_path, file_size, duration, status, recording_status, created_at) VALUES (?, 'self', 0, '', '', ?, ?, ?, 'completed', 'completed', NOW())",
            [$orderId, $filePath, $fileSize, $duration]
        );
        
        echo "✅ 录制结果已保存到数据库\n";
    }
    
    /**
     * 格式化字节数
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * 清理录制文件
     */
    public function cleanupRecording($orderId) {
        $recordingDir = "/tmp/fast_recording_$orderId";
        if (is_dir($recordingDir)) {
            $this->deleteDirectory($recordingDir);
            echo "✅ 录制文件已清理\n";
        }
    }
    
    /**
     * 删除目录
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
?>
