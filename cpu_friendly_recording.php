<?php
/**
 * CPU友好的录制方案
 * 使用队列和限流机制，避免CPU爆满
 */

require_once 'config/config.php';
require_once 'config/database.php';

class CPUFriendlyRecorder {
    private $db;
    private $maxConcurrent;
    private $cpuThreshold;
    
    public function __construct() {
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
        
        $this->maxConcurrent = $this->getSystemConfig('max_concurrent_processing', 1);
        $this->cpuThreshold = 70; // CPU使用率阈值
    }
    
    /**
     * 智能启动录制任务
     */
    public function startRecordingIntelligently($orderId) {
        // 检查系统资源
        if (!$this->checkSystemResources()) {
            error_log("⚠️ 系统资源不足，暂停启动新任务");
            return false;
        }
        
        // 获取当前运行的任务数
        $currentTasks = $this->getCurrentRunningTasks();
        
        if ($currentTasks >= $this->maxConcurrent) {
            error_log("⚠️ 当前任务数({$currentTasks})已达到最大并发数({$this->maxConcurrent})");
            return false;
        }
        
        // 获取下一个待处理的任务
        $nextTask = $this->getNextPendingTask($orderId);
        
        if ($nextTask) {
            $this->startSingleTask($nextTask);
            return true;
        }
        
        return false;
    }
    
    /**
     * 检查系统资源
     */
    private function checkSystemResources() {
        // 检查CPU使用率
        $cpuUsage = $this->getCPUUsage();
        if ($cpuUsage > $this->cpuThreshold) {
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
     * 获取下一个待处理的任务
     */
    private function getNextPendingTask($orderId) {
        return $this->db->fetchOne(
            "SELECT * FROM video_processing_queue 
             WHERE order_id = ? AND status = 'pending' AND task_type = 'record'
             ORDER BY priority DESC, created_at ASC 
             LIMIT 1",
            [$orderId]
        );
    }
    
    /**
     * 启动单个任务
     */
    private function startSingleTask($task) {
        try {
            // 更新任务状态
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'processing', started_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            // 启动录制
            $this->startRecording($task);
            
        } catch (Exception $e) {
            error_log("启动任务失败: " . $e->getMessage());
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
        
        error_log("🚀 启动录制任务: {$videoFileId}");
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
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $orderId = $argv[1];
    $recorder = new CPUFriendlyRecorder();
    $recorder->startRecordingIntelligently($orderId);
}
?>
