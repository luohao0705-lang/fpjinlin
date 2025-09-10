<?php
/**
 * 简单录制器 - 极简版本
 * 只专注于录制视频，避免复杂逻辑
 */

class SimpleRecorder {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * 录制视频 - 极简版本
     */
    public function recordVideo($orderId, $flvUrl, $maxDuration = 60) {
        echo "🎬 开始录制视频\n";
        echo "订单ID: $orderId\n";
        echo "FLV地址: $flvUrl\n";
        echo "最大时长: {$maxDuration}秒\n";
        echo "==================\n";
        
        // 1. 基本验证
        if (empty($flvUrl)) {
            return $this->error("FLV地址不能为空");
        }
        
        if (empty($orderId)) {
            return $this->error("订单ID不能为空");
        }
        
        // 2. 创建录制目录
        $recordingDir = "/tmp/record_$orderId";
        if (!$this->createDir($recordingDir)) {
            return $this->error("无法创建录制目录: $recordingDir");
        }
        
        // 3. 录制视频
        $outputFile = "$recordingDir/video.mp4";
        $result = $this->doRecording($flvUrl, $outputFile, $maxDuration);
        
        if (!$result['success']) {
            return $this->error($result['error']);
        }
        
        // 4. 检查录制结果
        if (!file_exists($outputFile) || filesize($outputFile) < 1024) {
            return $this->error("录制失败：文件不存在或文件过小");
        }
        
        // 5. 获取文件信息
        $fileSize = filesize($outputFile);
        $duration = $this->getDuration($outputFile);
        
        echo "✅ 录制成功！\n";
        echo "文件路径: $outputFile\n";
        echo "文件大小: " . $this->formatBytes($fileSize) . "\n";
        echo "视频时长: {$duration}秒\n";
        
        // 6. 保存到数据库
        $this->saveResult($orderId, $outputFile, $fileSize, $duration);
        
        return [
            'success' => true,
            'file_path' => $outputFile,
            'file_size' => $fileSize,
            'duration' => $duration
        ];
    }
    
    /**
     * 执行录制
     */
    private function doRecording($flvUrl, $outputFile, $maxDuration) {
        echo "📹 正在录制...\n";
        
        // 构建FFmpeg命令 - 使用最简单的参数
        $command = sprintf(
            'ffmpeg -i %s -t %d -c copy %s -y 2>&1',
            escapeshellarg($flvUrl),
            $maxDuration,
            escapeshellarg($outputFile)
        );
        
        echo "执行命令: $command\n";
        
        // 执行命令
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            return [
                'success' => false,
                'error' => "FFmpeg失败 (返回码: $returnCode): $errorMsg"
            ];
        }
        
        echo "✅ FFmpeg执行完成\n";
        return ['success' => true];
    }
    
    /**
     * 创建目录
     */
    private function createDir($dir) {
        if (is_dir($dir)) {
            return true;
        }
        
        if (!mkdir($dir, 0777, true)) {
            return false;
        }
        
        return is_writable($dir);
    }
    
    /**
     * 获取视频时长
     */
    private function getDuration($filePath) {
        $command = "ffprobe -v quiet -show_entries format=duration -of csv=p=0 " . escapeshellarg($filePath);
        $output = [];
        exec($command, $output);
        return intval($output[0] ?? 0);
    }
    
    /**
     * 获取视频详细信息
     */
    public function getVideoInfo($filePath) {
        if (!file_exists($filePath)) {
            return null;
        }
        
        $command = "ffprobe -v quiet -print_format json -show_format -show_streams " . escapeshellarg($filePath);
        $output = [];
        exec($command, $output);
        
        $json = implode('', $output);
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['streams'][0])) {
            return null;
        }
        
        $stream = $data['streams'][0];
        $format = $data['format'];
        
        return [
            'width' => $stream['width'] ?? 0,
            'height' => $stream['height'] ?? 0,
            'duration' => intval($format['duration'] ?? 0),
            'size' => intval($format['size'] ?? 0),
            'bitrate' => intval($format['bit_rate'] ?? 0),
            'codec' => $stream['codec_name'] ?? 'unknown'
        ];
    }
    
    /**
     * 保存结果到数据库
     */
    private function saveResult($orderId, $filePath, $fileSize, $duration) {
        try {
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
            
            echo "✅ 数据库保存成功\n";
            
        } catch (Exception $e) {
            echo "⚠️ 数据库保存失败: " . $e->getMessage() . "\n";
        }
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
     * 返回错误
     */
    private function error($message) {
        echo "❌ 错误: $message\n";
        return [
            'success' => false,
            'error' => $message
        ];
    }
    
    /**
     * 清理录制文件
     */
    public function cleanup($orderId) {
        $recordingDir = "/tmp/record_$orderId";
        if (is_dir($recordingDir)) {
            $this->deleteDir($recordingDir);
            echo "✅ 清理完成\n";
        }
    }
    
    /**
     * 删除目录
     */
    private function deleteDir($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
?>
