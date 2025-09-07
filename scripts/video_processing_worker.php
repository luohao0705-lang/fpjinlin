<?php
/**
 * 视频处理工作进程
 * 复盘精灵系统 - 后台处理
 */

// 设置脚本运行时间限制
set_time_limit(0);
ini_set('memory_limit', '512M');

// 引入配置文件
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

class VideoProcessingWorker {
    private $db;
    private $maxConcurrent;
    private $running = true;
    
    public function __construct() {
        $this->db = new Database();
        $this->maxConcurrent = getSystemConfig('max_concurrent_processing', 3);
        
        // 设置信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }
    }
    
    /**
     * 处理信号
     */
    public function handleSignal($signal) {
        error_log("收到信号 {$signal}，准备停止工作进程");
        $this->running = false;
    }
    
    /**
     * 启动工作进程
     */
    public function start() {
        error_log("视频处理工作进程启动");
        
        while ($this->running) {
            try {
                // 获取待处理的任务
                $tasks = $this->getPendingTasks();
                
                if (empty($tasks)) {
                    // 没有任务，等待5秒
                    sleep(5);
                    continue;
                }
                
                // 处理任务
                foreach ($tasks as $task) {
                    if (!$this->running) {
                        break;
                    }
                    
                    $this->processTask($task);
                }
                
            } catch (Exception $e) {
                error_log("工作进程异常: " . $e->getMessage());
                sleep(10); // 异常后等待10秒
            }
        }
        
        error_log("视频处理工作进程停止");
    }
    
    /**
     * 获取待处理任务
     */
    private function getPendingTasks() {
        // 获取正在处理的任务数量
        $processingCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM video_processing_queue WHERE status = 'processing'"
        )['count'];
        
        if ($processingCount >= $this->maxConcurrent) {
            return [];
        }
        
        // 获取待处理任务
        $availableSlots = $this->maxConcurrent - $processingCount;
        
        return $this->db->fetchAll(
            "SELECT * FROM video_processing_queue 
             WHERE status = 'pending' 
             ORDER BY priority DESC, created_at ASC 
             LIMIT ?",
            [$availableSlots]
        );
    }
    
    /**
     * 处理单个任务
     */
    private function processTask($task) {
        try {
            error_log("开始处理任务: {$task['id']} - {$task['task_type']}");
            
            // 更新任务状态为处理中
            $this->updateTaskStatus($task['id'], 'processing');
            
            // 根据任务类型处理
            $result = $this->executeTask($task);
            
            if ($result) {
                // 任务成功
                $this->updateTaskStatus($task['id'], 'completed');
                error_log("任务完成: {$task['id']} - {$task['task_type']}");
            } else {
                // 任务失败
                $this->handleTaskFailure($task);
            }
            
        } catch (Exception $e) {
            error_log("任务处理失败: {$task['id']} - " . $e->getMessage());
            $this->handleTaskFailure($task, $e->getMessage());
        }
    }
    
    /**
     * 执行具体任务
     */
    private function executeTask($task) {
        switch ($task['task_type']) {
            case 'download':
                return $this->executeDownloadTask($task);
            case 'transcode':
                return $this->executeTranscodeTask($task);
            case 'segment':
                return $this->executeSegmentTask($task);
            case 'asr':
                return $this->executeAsrTask($task);
            case 'analysis':
                return $this->executeAnalysisTask($task);
            case 'report':
                return $this->executeReportTask($task);
            default:
                throw new Exception("未知任务类型: {$task['task_type']}");
        }
    }
    
    /**
     * 执行下载任务
     */
    private function executeDownloadTask($task) {
        $orderId = $task['order_id'];
        
        // 获取视频文件
        $videoFiles = $this->db->fetchAll(
            "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
            [$orderId]
        );
        
        $videoProcessor = new VideoProcessor();
        
        foreach ($videoFiles as $videoFile) {
            if ($videoFile['flv_url']) {
                $videoProcessor->downloadVideo($videoFile['id'], $videoFile['flv_url']);
            }
        }
        
        return true;
    }
    
    /**
     * 执行转码任务
     */
    private function executeTranscodeTask($task) {
        $orderId = $task['order_id'];
        
        // 获取视频文件
        $videoFiles = $this->db->fetchAll(
            "SELECT * FROM video_files WHERE order_id = ? AND status = 'completed' ORDER BY video_type, video_index",
            [$orderId]
        );
        
        $videoProcessor = new VideoProcessor();
        
        foreach ($videoFiles as $videoFile) {
            $videoProcessor->transcodeVideo($videoFile['id']);
        }
        
        return true;
    }
    
    /**
     * 执行切片任务
     */
    private function executeSegmentTask($task) {
        $orderId = $task['order_id'];
        
        // 获取视频文件
        $videoFiles = $this->db->fetchAll(
            "SELECT * FROM video_files WHERE order_id = ? AND status = 'completed' ORDER BY video_type, video_index",
            [$orderId]
        );
        
        $videoProcessor = new VideoProcessor();
        
        foreach ($videoFiles as $videoFile) {
            $videoProcessor->segmentVideo($videoFile['id']);
        }
        
        return true;
    }
    
    /**
     * 执行语音识别任务
     */
    private function executeAsrTask($task) {
        $orderId = $task['order_id'];
        
        // 获取切片
        $segments = $this->db->fetchAll(
            "SELECT vs.* FROM video_segments vs 
             LEFT JOIN video_files vf ON vs.video_file_id = vf.id 
             WHERE vf.order_id = ? AND vs.status = 'completed' 
             ORDER BY vf.video_type, vf.video_index, vs.segment_index",
            [$orderId]
        );
        
        $whisperService = new WhisperService();
        
        foreach ($segments as $segment) {
            $whisperService->processSegment($segment['id']);
        }
        
        return true;
    }
    
    /**
     * 执行视频分析任务
     */
    private function executeAnalysisTask($task) {
        $orderId = $task['order_id'];
        
        // 获取切片
        $segments = $this->db->fetchAll(
            "SELECT vs.* FROM video_segments vs 
             LEFT JOIN video_files vf ON vs.video_file_id = vf.id 
             WHERE vf.order_id = ? AND vs.status = 'completed' 
             ORDER BY vf.video_type, vf.video_index, vs.segment_index",
            [$orderId]
        );
        
        $qwenOmniService = new QwenOmniService();
        
        foreach ($segments as $segment) {
            $qwenOmniService->analyzeSegment($segment['id']);
        }
        
        return true;
    }
    
    /**
     * 执行报告生成任务
     */
    private function executeReportTask($task) {
        $orderId = $task['order_id'];
        
        $videoAnalysisEngine = new VideoAnalysisEngine();
        $videoAnalysisEngine->processVideoAnalysis($orderId);
        
        return true;
    }
    
    /**
     * 更新任务状态
     */
    private function updateTaskStatus($taskId, $status, $errorMessage = null) {
        $sql = "UPDATE video_processing_queue SET status = ?";
        $params = [$status];
        
        if ($status === 'processing') {
            $sql .= ", started_at = NOW()";
        } elseif ($status === 'completed') {
            $sql .= ", completed_at = NOW()";
        } elseif ($status === 'failed' && $errorMessage) {
            $sql .= ", error_message = ?";
            $params[] = $errorMessage;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $taskId;
        
        $this->db->query($sql, $params);
    }
    
    /**
     * 处理任务失败
     */
    private function handleTaskFailure($task, $errorMessage = null) {
        $retryCount = $task['retry_count'] + 1;
        $maxRetries = $task['max_retries'];
        
        if ($retryCount < $maxRetries) {
            // 重试
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'retry', retry_count = ?, error_message = ? WHERE id = ?",
                [$retryCount, $errorMessage, $task['id']]
            );
            error_log("任务重试: {$task['id']} - 第{$retryCount}次");
        } else {
            // 失败
            $this->updateTaskStatus($task['id'], 'failed', $errorMessage);
            error_log("任务失败: {$task['id']} - 超过最大重试次数");
        }
    }
}

// 启动工作进程
if (php_sapi_name() === 'cli') {
    $worker = new VideoProcessingWorker();
    $worker->start();
} else {
    echo "此脚本只能在命令行模式下运行";
    exit(1);
}
