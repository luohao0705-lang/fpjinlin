<?php
/**
 * 任务队列处理器
 * 智能处理视频录制任务，避免CPU爆满
 */

require_once 'config/config.php';
require_once 'config/database.php';

class TaskQueueProcessor {
    private $db;
    private $maxConcurrent;
    private $processingInterval = 5; // 处理间隔（秒）
    
    public function __construct() {
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
        
        $this->maxConcurrent = $this->getSystemConfig('max_concurrent_processing', 1);
    }
    
    /**
     * 启动队列处理器
     */
    public function startProcessing() {
        error_log("🚀 启动任务队列处理器，最大并发数: {$this->maxConcurrent}");
        
        while (true) {
            try {
                // 处理待处理的任务
                $this->processPendingTasks();
                
                // 等待下次处理
                sleep($this->processingInterval);
                
            } catch (Exception $e) {
                error_log("❌ 队列处理器错误: " . $e->getMessage());
                sleep(10); // 出错时等待更长时间
            }
        }
    }
    
    /**
     * 处理待处理的任务
     */
    private function processPendingTasks() {
        // 检查当前运行的任务数
        $currentTasks = $this->getCurrentRunningTasks();
        
        if ($currentTasks >= $this->maxConcurrent) {
            return; // 已达到最大并发数
        }
        
        // 检查系统资源
        if (!$this->checkSystemResources()) {
            return; // 系统资源不足
        }
        
        // 获取下一个待处理的任务
        $nextTask = $this->getNextPendingTask();
        
        if ($nextTask) {
            $this->startTask($nextTask);
        }
    }
    
    /**
     * 获取当前运行的任务数
     */
    private function getCurrentRunningTasks() {
        try {
            $output = [];
            exec('ps aux | grep ffmpeg | grep -v grep | wc -l', $output);
            return intval($output[0] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * 检查系统资源
     */
    private function checkSystemResources() {
        // 检查CPU使用率
        $cpuUsage = $this->getCPUUsage();
        if ($cpuUsage > 70) {
            error_log("⚠️ CPU使用率过高: {$cpuUsage}%");
            return false;
        }
        
        // 检查内存使用率
        $memoryUsage = $this->getMemoryUsage();
        if ($memoryUsage > 80) {
            error_log("⚠️ 内存使用率过高: {$memoryUsage}%");
            return false;
        }
        
        return true;
    }
    
    /**
     * 获取CPU使用率
     */
    private function getCPUUsage() {
        try {
            $output = [];
            exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2}' | awk -F'%' '{print $1}'", $output);
            return floatval($output[0] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * 获取内存使用率
     */
    private function getMemoryUsage() {
        try {
            $output = [];
            exec("free | grep Mem | awk '{printf \"%.2f\", $3/$2 * 100.0}'", $output);
            return floatval($output[0] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * 获取下一个待处理的任务
     */
    private function getNextPendingTask() {
        return $this->db->fetchOne(
            "SELECT * FROM video_processing_queue 
             WHERE status = 'pending' AND task_type = 'record'
             ORDER BY priority DESC, created_at ASC 
             LIMIT 1"
        );
    }
    
    /**
     * 启动任务
     */
    private function startTask($task) {
        try {
            // 更新任务状态
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'processing', started_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            // 启动录制
            $this->startRecording($task);
            
            error_log("🚀 启动任务: {$task['id']}, 类型: {$task['task_type']}");
            
        } catch (Exception $e) {
            error_log("❌ 启动任务失败: " . $e->getMessage());
        }
    }
    
    /**
     * 启动录制
     */
    private function startRecording($task) {
        $taskData = json_decode($task['task_data'], true);
        $videoFileId = $taskData['video_file_id'];
        
        // 使用后台进程启动录制
        $command = "php -f record_single_video.php {$videoFileId} > /dev/null 2>&1 &";
        exec($command);
    }
    
    /**
     * 获取系统配置
     */
    private function getSystemConfig($key, $defaultValue = null) {
        try {
            $config = $this->db->fetchOne(
                "SELECT config_value FROM system_config WHERE config_key = ?",
                [$key]
            );
            
            if ($config && isset($config['config_value'])) {
                return intval($config['config_value']);
            }
            
            return $defaultValue;
        } catch (Exception $e) {
            return $defaultValue;
        }
    }
}

// 如果直接运行此脚本
if (php_sapi_name() === 'cli') {
    $processor = new TaskQueueProcessor();
    $processor->startProcessing();
}
?>
