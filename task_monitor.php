<?php
/**
 * 任务监控和恢复工具
 * 监控任务状态，自动恢复卡住的任务
 */

require_once 'config/config.php';
require_once 'config/database.php';

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

class TaskMonitor {
    private $db;
    
    public function __construct() {
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
    }
    
    /**
     * 监控所有任务状态
     */
    public function monitorTasks() {
        echo "🔍 开始监控任务状态...\n\n";
        
        // 检查处理中超过30分钟的任务
        $stuckTasks = $this->db->fetchAll(
            "SELECT * FROM video_processing_queue 
             WHERE status = 'processing' 
             AND started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
        );
        
        if (!empty($stuckTasks)) {
            echo "⚠️ 发现卡住的任务:\n";
            foreach ($stuckTasks as $task) {
                echo "- 任务ID: {$task['id']}, 类型: {$task['task_type']}, 开始时间: {$task['started_at']}\n";
                $this->recoverStuckTask($task);
            }
        } else {
            echo "✅ 没有发现卡住的任务\n";
        }
        
        // 检查失败的任务
        $failedTasks = $this->db->fetchAll(
            "SELECT * FROM video_processing_queue 
             WHERE status = 'failed' 
             AND retry_count < 3
             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        if (!empty($failedTasks)) {
            echo "\n🔄 发现可重试的失败任务:\n";
            foreach ($failedTasks as $task) {
                echo "- 任务ID: {$task['id']}, 类型: {$task['task_type']}, 重试次数: {$task['retry_count']}\n";
                $this->retryFailedTask($task);
            }
        }
        
        // 显示系统资源状态
        $this->showSystemStatus();
    }
    
    /**
     * 恢复卡住的任务
     */
    private function recoverStuckTask($task) {
        echo "🔧 恢复卡住的任务: {$task['id']}\n";
        
        // 检查FFmpeg进程是否还在运行
        $isFFmpegRunning = $this->checkFFmpegProcess($task['id']);
        
        if ($isFFmpegRunning) {
            echo "  - FFmpeg进程仍在运行，等待完成...\n";
            return;
        }
        
        // 重置任务状态
        $this->db->query(
            "UPDATE video_processing_queue 
             SET status = 'pending', started_at = NULL, error_message = '任务超时，已重置' 
             WHERE id = ?",
            [$task['id']]
        );
        
        echo "  - 任务已重置为待处理状态\n";
    }
    
    /**
     * 重试失败的任务
     */
    private function retryFailedTask($task) {
        echo "🔄 重试失败的任务: {$task['id']}\n";
        
        // 重置任务状态
        $this->db->query(
            "UPDATE video_processing_queue 
             SET status = 'pending', started_at = NULL, error_message = NULL 
             WHERE id = ?",
            [$task['id']]
        );
        
        echo "  - 任务已重置为待处理状态\n";
    }
    
    /**
     * 检查FFmpeg进程是否在运行
     */
    private function checkFFmpegProcess($taskId) {
        $output = [];
        exec("ps aux | grep ffmpeg | grep -v grep", $output);
        
        foreach ($output as $line) {
            if (strpos($line, "video_{$taskId}_") !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 显示系统状态
     */
    private function showSystemStatus() {
        echo "\n📊 系统状态:\n";
        
        // 内存使用率
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        echo "- 内存使用: " . $this->formatBytes($memoryUsage) . " / " . $memoryLimit . "\n";
        
        // CPU负载
        $loadAvg = sys_getloadavg();
        echo "- CPU负载: " . implode(', ', $loadAvg) . "\n";
        
        // 磁盘空间
        $freeSpace = disk_free_space(sys_get_temp_dir());
        echo "- 磁盘空间: " . $this->formatBytes($freeSpace) . " 可用\n";
        
        // FFmpeg进程数
        $ffmpegProcesses = $this->getFFmpegProcessCount();
        echo "- FFmpeg进程: {$ffmpegProcesses} 个\n";
        
        // 任务统计
        $taskStats = $this->getTaskStats();
        echo "- 任务统计: 待处理 {$taskStats['pending']} 个, 处理中 {$taskStats['processing']} 个, 已完成 {$taskStats['completed']} 个, 失败 {$taskStats['failed']} 个\n";
    }
    
    /**
     * 获取任务统计
     */
    private function getTaskStats() {
        $stats = $this->db->fetchAll(
            "SELECT status, COUNT(*) as count 
             FROM video_processing_queue 
             GROUP BY status"
        );
        
        $result = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        
        foreach ($stats as $stat) {
            $result[$stat['status']] = $stat['count'];
        }
        
        return $result;
    }
    
    /**
     * 获取FFmpeg进程数量
     */
    private function getFFmpegProcessCount() {
        $output = [];
        exec('ps aux | grep ffmpeg | grep -v grep | wc -l', $output);
        return intval($output[0] ?? 0);
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

// 运行监控
if (php_sapi_name() === 'cli') {
    $monitor = new TaskMonitor();
    $monitor->monitorTasks();
} else {
    echo "请在命令行中运行此脚本\n";
}
?>
