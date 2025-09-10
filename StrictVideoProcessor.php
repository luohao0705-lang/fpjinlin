<?php
/**
 * 严谨的视频处理系统
 * 确保顺序执行，严格控制资源使用
 */

class StrictVideoProcessor {
    private $db;
    private $maxConcurrent = 1; // 最大并发数
    private $maxRetries = 3;    // 最大重试次数
    private $retryDelay = 5;    // 重试延迟（秒）
    
    public function __construct() {
        $this->db = new Database();
        $this->maxConcurrent = $this->getSystemConfig('max_concurrent_processing', 1);
    }
    
    /**
     * 启动视频分析 - 严格顺序执行
     */
    public function startAnalysis($orderId) {
        try {
            echo "🎬 开始严谨的视频分析流程\n";
            echo "订单ID: $orderId\n";
            echo "==================\n\n";
            
            // 1. 验证订单
            $order = $this->validateOrder($orderId);
            
            // 2. 检查系统资源
            $this->checkSystemResources();
            
            // 3. 创建处理任务（只创建录制任务）
            $this->createRecordingTasks($orderId);
            
            // 4. 开始顺序处理
            $this->processSequentially($orderId);
            
            return [
                'success' => true,
                'message' => '视频分析流程已启动'
            ];
            
        } catch (Exception $e) {
            error_log("启动视频分析失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 验证订单
     */
    private function validateOrder($orderId) {
        $order = $this->db->fetchOne(
            "SELECT * FROM video_analysis_orders WHERE id = ?",
            [$orderId]
        );
        
        if (!$order) {
            throw new Exception('订单不存在');
        }
        
        if (empty($order['self_flv_url'])) {
            throw new Exception('FLV地址未配置');
        }
        
        if (!in_array($order['status'], ['reviewing', 'processing', 'failed', 'stopped'])) {
            throw new Exception('订单状态不允许处理');
        }
        
        return $order;
    }
    
    /**
     * 检查系统资源
     */
    private function checkSystemResources() {
        // 检查CPU负载
        $loadAvg = sys_getloadavg();
        if ($loadAvg[0] > 2.0) {
            throw new Exception('CPU负载过高，请稍后重试');
        }
        
        // 检查内存使用
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        if ($memoryUsage > $memoryLimit * 0.8) {
            throw new Exception('内存使用率过高，请稍后重试');
        }
        
        // 检查磁盘空间
        $freeSpace = disk_free_space(sys_get_temp_dir());
        if ($freeSpace < 2 * 1024 * 1024 * 1024) { // 少于2GB
            throw new Exception('磁盘空间不足，请清理临时文件');
        }
        
        // 检查当前FFmpeg进程数
        $currentProcesses = $this->getCurrentFFmpegProcessCount();
        if ($currentProcesses >= $this->maxConcurrent) {
            throw new Exception("当前FFmpeg进程数({$currentProcesses})已达到最大并发数({$this->maxConcurrent})");
        }
        
        echo "✅ 系统资源检查通过\n";
        echo "CPU负载: " . round($loadAvg[0], 2) . "\n";
        echo "内存使用: " . $this->formatBytes($memoryUsage) . " / " . $this->formatBytes($memoryLimit) . "\n";
        echo "磁盘空间: " . $this->formatBytes($freeSpace) . "\n";
        echo "FFmpeg进程: {$currentProcesses}/{$this->maxConcurrent}\n\n";
    }
    
    /**
     * 创建录制任务
     */
    private function createRecordingTasks($orderId) {
        // 清理旧任务
        $this->db->query(
            "DELETE FROM video_processing_queue WHERE order_id = ?",
            [$orderId]
        );
        
        // 获取视频文件
        $videoFiles = $this->db->fetchAll(
            "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
            [$orderId]
        );
        
        if (empty($videoFiles)) {
            throw new Exception('没有找到视频文件记录');
        }
        
        // 为每个视频文件创建录制任务
        foreach ($videoFiles as $videoFile) {
            $this->db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, 'record', ?, 10, 'pending', NOW())",
                [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
            );
        }
        
        echo "✅ 创建了 " . count($videoFiles) . " 个录制任务\n\n";
    }
    
    /**
     * 顺序处理任务
     */
    private function processSequentially($orderId) {
        $tasks = $this->db->fetchAll(
            "SELECT * FROM video_processing_queue 
             WHERE order_id = ? AND status = 'pending' 
             ORDER BY priority DESC, created_at ASC",
            [$orderId]
        );
        
        if (empty($tasks)) {
            echo "⚠️ 没有待处理的任务\n";
            return;
        }
        
        echo "📋 开始顺序处理 " . count($tasks) . " 个任务\n";
        echo "==================\n\n";
        
        foreach ($tasks as $index => $task) {
            echo "🔄 处理任务 " . ($index + 1) . "/" . count($tasks) . ": {$task['task_type']}\n";
            
            try {
                $this->processTask($task);
                echo "✅ 任务完成\n\n";
                
                // 任务间暂停，避免资源冲突
                if ($index < count($tasks) - 1) {
                    sleep(2);
                }
                
            } catch (Exception $e) {
                echo "❌ 任务失败: " . $e->getMessage() . "\n\n";
                
                // 更新任务状态
                $this->db->query(
                    "UPDATE video_processing_queue SET status = 'failed', error_message = ? WHERE id = ?",
                    [$e->getMessage(), $task['id']]
                );
                
                // 如果是录制任务失败，停止整个流程
                if ($task['task_type'] === 'record') {
                    throw new Exception("录制任务失败，停止处理: " . $e->getMessage());
                }
            }
        }
        
        echo "🎉 所有任务处理完成！\n";
    }
    
    /**
     * 处理单个任务
     */
    private function processTask($task) {
        $startTime = microtime(true);
        
        // 更新任务状态
        $this->db->query(
            "UPDATE video_processing_queue SET status = 'processing', started_at = NOW() WHERE id = ?",
            [$task['id']]
        );
        
        try {
            $taskData = json_decode($task['task_data'], true);
            
            switch ($task['task_type']) {
                case 'record':
                    $this->processRecordTask($taskData);
                    break;
                default:
                    throw new Exception('未知任务类型: ' . $task['task_type']);
            }
            
            // 更新任务状态为完成
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'completed', completed_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            $duration = round(microtime(true) - $startTime, 2);
            echo "⏱️ 耗时: {$duration}秒\n";
            
        } catch (Exception $e) {
            // 更新任务状态为失败
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'failed', error_message = ? WHERE id = ?",
                [$e->getMessage(), $task['id']]
            );
            throw $e;
        }
    }
    
    /**
     * 处理录制任务
     */
    private function processRecordTask($taskData) {
        $videoFileId = $taskData['video_file_id'];
        $videoFile = $this->db->fetchOne(
            "SELECT * FROM video_files WHERE id = ?",
            [$videoFileId]
        );
        
        if (!$videoFile || empty($videoFile['flv_url'])) {
            throw new Exception('视频文件或FLV地址不存在');
        }
        
        echo "📹 开始录制视频\n";
        echo "视频文件ID: $videoFileId\n";
        echo "FLV地址: " . substr($videoFile['flv_url'], 0, 50) . "...\n";
        
        // 使用SimpleRecorder
        require_once 'SimpleRecorder.php';
        $recorder = new SimpleRecorder();
        
        // 获取最大录制时长
        $maxDuration = $this->getSystemConfig('max_video_duration', 60);
        
        // 执行录制
        $result = $recorder->recordVideo($videoFileId, $videoFile['flv_url'], $maxDuration);
        
        if (!$result['success']) {
            throw new Exception($result['error']);
        }
        
        // 更新视频文件记录
        $this->db->query(
            "UPDATE video_files SET 
             status = 'completed', 
             file_path = ?, 
             file_size = ?, 
             duration = ?,
             recording_status = 'completed',
             recording_completed_at = NOW()
             WHERE id = ?",
            [
                $result['file_path'], 
                $result['file_size'], 
                $result['duration'], 
                $videoFileId
            ]
        );
        
        echo "✅ 录制成功！\n";
        echo "文件路径: {$result['file_path']}\n";
        echo "文件大小: " . $this->formatBytes($result['file_size']) . "\n";
        echo "视频时长: {$result['duration']}秒\n";
    }
    
    /**
     * 获取当前FFmpeg进程数
     */
    private function getCurrentFFmpegProcessCount() {
        try {
            $output = [];
            exec('ps aux | grep ffmpeg | grep -v grep | wc -l', $output);
            return intval($output[0] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * 获取系统配置
     */
    private function getSystemConfig($key, $defaultValue = null) {
        try {
            $config = $this->db->fetchOne(
                "SELECT config_value FROM system_configs WHERE config_key = ?",
                [$key]
            );
            
            if ($config && isset($config['config_value'])) {
                return $config['config_value'];
            }
            
            return $defaultValue;
        } catch (Exception $e) {
            return $defaultValue;
        }
    }
    
    /**
     * 解析内存限制
     */
    private function parseMemoryLimit($memoryLimit) {
        $unit = strtolower(substr($memoryLimit, -1));
        $value = intval($memoryLimit);
        
        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
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
}
?>
