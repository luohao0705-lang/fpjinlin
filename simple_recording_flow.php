<?php
/**
 * 简化的录制流程
 * 只专注于录制视频，其他步骤后续处理
 */

require_once 'config/database.php';

class SimpleRecordingFlow {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * 启动录制流程
     */
    public function startRecording($orderId, $flvUrl) {
        echo "🎬 开始录制流程\n";
        echo "订单ID: $orderId\n";
        echo "FLV地址: $flvUrl\n";
        echo "==================\n\n";
        
        try {
            // 1. 检查订单状态
            $order = $this->getOrder($orderId);
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            // 2. 更新订单状态为录制中
            $this->updateOrderStatus($orderId, 'recording');
            
            // 3. 创建录制任务
            $taskId = $this->createRecordingTask($orderId, $flvUrl);
            
            // 4. 开始录制
            $this->executeRecording($taskId, $flvUrl);
            
            echo "✅ 录制流程启动成功\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ 录制失败: " . $e->getMessage() . "\n";
            $this->updateOrderStatus($orderId, 'failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取订单信息
     */
    private function getOrder($orderId) {
        return $this->db->fetchOne(
            "SELECT * FROM video_analysis_orders WHERE id = ?",
            [$orderId]
        );
    }
    
    /**
     * 更新订单状态
     */
    private function updateOrderStatus($orderId, $status, $errorMessage = null) {
        $sql = "UPDATE video_analysis_orders SET status = ?";
        $params = [$status];
        
        if ($status === 'recording') {
            $sql .= ", processing_started_at = NOW()";
        } elseif ($status === 'completed') {
            $sql .= ", completed_at = NOW()";
        } elseif ($status === 'failed' && $errorMessage) {
            $sql .= ", error_message = ?";
            $params[] = $errorMessage;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $orderId;
        
        $this->db->query($sql, $params);
    }
    
    /**
     * 创建录制任务
     */
    private function createRecordingTask($orderId, $flvUrl) {
        // 删除旧的任务
        $this->db->query("DELETE FROM video_processing_queue WHERE order_id = ?", [$orderId]);
        
        // 创建新的录制任务
        return $this->db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, 'record', ?, 10, 'pending', NOW())",
            [$orderId, json_encode(['flv_url' => $flvUrl])]
        );
    }
    
    /**
     * 执行录制
     */
    private function executeRecording($taskId, $flvUrl) {
        echo "📹 开始录制视频...\n";
        
        // 更新任务状态
        $this->db->query(
            "UPDATE video_processing_queue SET status = 'processing', started_at = NOW() WHERE id = ?",
            [$taskId]
        );
        
        // 创建录制目录
        $recordingDir = "/tmp/video_recording_$taskId";
        if (!is_dir($recordingDir)) {
            mkdir($recordingDir, 0777, true);
        }
        
        $outputFile = "$recordingDir/video.mp4";
        
        try {
            // 使用FFmpeg直接录制
            $command = "ffmpeg -i '$flvUrl' -t 3600 -c copy -avoid_negative_ts make_zero -fflags +genpts '$outputFile' -y 2>&1";
            
            echo "执行命令: $command\n";
            
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($outputFile)) {
                // 录制成功
                $fileSize = filesize($outputFile);
                $duration = $this->getVideoDuration($outputFile);
                
                echo "✅ 录制成功\n";
                echo "文件大小: " . $this->formatBytes($fileSize) . "\n";
                echo "视频时长: {$duration}秒\n";
                
                // 更新任务状态
                $this->db->query(
                    "UPDATE video_processing_queue SET status = 'completed', completed_at = NOW() WHERE id = ?",
                    [$taskId]
                );
                
                // 更新订单状态
                $orderId = $this->db->fetchOne("SELECT order_id FROM video_processing_queue WHERE id = ?", [$taskId])['order_id'];
                $this->updateOrderStatus($orderId, 'completed');
                
                // 保存视频文件信息
                $this->saveVideoFile($orderId, $outputFile, $fileSize, $duration);
                
                echo "🎉 录制流程完成！\n";
                
            } else {
                throw new Exception("FFmpeg录制失败，返回码: $returnCode\n" . implode("\n", $output));
            }
            
        } catch (Exception $e) {
            // 录制失败
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'failed', error_message = ? WHERE id = ?",
                [$e->getMessage(), $taskId]
            );
            
            $orderId = $this->db->fetchOne("SELECT order_id FROM video_processing_queue WHERE id = ?", [$taskId])['order_id'];
            $this->updateOrderStatus($orderId, 'failed', $e->getMessage());
            
            throw $e;
        }
    }
    
    /**
     * 获取视频时长
     */
    private function getVideoDuration($filePath) {
        $command = "ffprobe -v quiet -show_entries format=duration -of csv=p=0 '$filePath'";
        $output = [];
        exec($command, $output);
        return intval($output[0] ?? 0);
    }
    
    /**
     * 保存视频文件信息
     */
    private function saveVideoFile($orderId, $filePath, $fileSize, $duration) {
        $this->db->insert(
            "INSERT INTO video_files (order_id, video_type, video_index, original_url, flv_url, file_path, file_size, duration, status, recording_status, created_at) VALUES (?, 'self', 0, '', '', ?, ?, ?, 'completed', 'completed', NOW())",
            [$orderId, $filePath, $fileSize, $duration]
        );
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
}

// 测试脚本
if (php_sapi_name() === 'cli') {
    echo "🧪 测试简化录制流程\n";
    echo "==================\n\n";
    
    $recorder = new SimpleRecordingFlow();
    
    // 测试参数
    $orderId = 44; // 使用现有的订单ID
    $flvUrl = "https://live.douyin.com/test?expire=" . (time() + 3600);
    
    $recorder->startRecording($orderId, $flvUrl);
}
?>
