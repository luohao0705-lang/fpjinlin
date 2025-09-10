<?php
/**
 * 系统监控工具
 * 实时监控CPU、内存、磁盘、FFmpeg进程等
 */

class SystemMonitor {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * 获取系统状态
     */
    public function getSystemStatus() {
        $status = [
            'timestamp' => date('Y-m-d H:i:s'),
            'cpu' => $this->getCpuStatus(),
            'memory' => $this->getMemoryStatus(),
            'disk' => $this->getDiskStatus(),
            'ffmpeg' => $this->getFFmpegStatus(),
            'database' => $this->getDatabaseStatus(),
            'tasks' => $this->getTaskStatus()
        ];
        
        return $status;
    }
    
    /**
     * 获取CPU状态
     */
    private function getCpuStatus() {
        $loadAvg = sys_getloadavg();
        return [
            'load_1min' => round($loadAvg[0], 2),
            'load_5min' => round($loadAvg[1], 2),
            'load_15min' => round($loadAvg[2], 2),
            'status' => $loadAvg[0] > 4.0 ? 'high' : ($loadAvg[0] > 2.0 ? 'medium' : 'normal')
        ];
    }
    
    /**
     * 获取内存状态
     */
    private function getMemoryStatus() {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $usagePercent = ($memoryUsage / $memoryLimit) * 100;
        
        return [
            'usage' => $memoryUsage,
            'limit' => $memoryLimit,
            'usage_percent' => round($usagePercent, 2),
            'usage_formatted' => $this->formatBytes($memoryUsage),
            'limit_formatted' => $this->formatBytes($memoryLimit),
            'status' => $usagePercent > 80 ? 'high' : ($usagePercent > 60 ? 'medium' : 'normal')
        ];
    }
    
    /**
     * 获取磁盘状态
     */
    private function getDiskStatus() {
        $tempDir = sys_get_temp_dir();
        $totalSpace = disk_total_space($tempDir);
        $freeSpace = disk_free_space($tempDir);
        $usedSpace = $totalSpace - $freeSpace;
        $usagePercent = ($usedSpace / $totalSpace) * 100;
        
        return [
            'total' => $totalSpace,
            'used' => $usedSpace,
            'free' => $freeSpace,
            'usage_percent' => round($usagePercent, 2),
            'total_formatted' => $this->formatBytes($totalSpace),
            'used_formatted' => $this->formatBytes($usedSpace),
            'free_formatted' => $this->formatBytes($freeSpace),
            'status' => $usagePercent > 90 ? 'high' : ($usagePercent > 80 ? 'medium' : 'normal')
        ];
    }
    
    /**
     * 获取FFmpeg状态
     */
    private function getFFmpegStatus() {
        $processes = $this->getFFmpegProcesses();
        $maxConcurrent = $this->getSystemConfig('max_concurrent_processing', 1);
        
        return [
            'current' => count($processes),
            'max' => $maxConcurrent,
            'status' => count($processes) >= $maxConcurrent ? 'max' : 'normal',
            'processes' => $processes
        ];
    }
    
    /**
     * 获取FFmpeg进程列表
     */
    private function getFFmpegProcesses() {
        $processes = [];
        try {
            $output = [];
            exec('ps aux | grep ffmpeg | grep -v grep', $output);
            
            foreach ($output as $line) {
                if (preg_match('/\s+(\d+)\s+.*?ffmpeg\s+(.*)/', $line, $matches)) {
                    $processes[] = [
                        'pid' => $matches[1],
                        'command' => trim($matches[2])
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("获取FFmpeg进程失败: " . $e->getMessage());
        }
        
        return $processes;
    }
    
    /**
     * 获取数据库状态
     */
    private function getDatabaseStatus() {
        try {
            $result = $this->db->fetchOne("SELECT 1 as test");
            return [
                'status' => 'connected',
                'test' => $result ? 'ok' : 'failed'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取任务状态
     */
    private function getTaskStatus() {
        try {
            $stats = $this->db->fetchAll(
                "SELECT status, COUNT(*) as count FROM video_processing_queue GROUP BY status"
            );
            
            $taskStats = [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'stopped' => 0
            ];
            
            foreach ($stats as $stat) {
                $taskStats[$stat['status']] = $stat['count'];
            }
            
            return $taskStats;
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
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
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * 显示系统状态
     */
    public function displayStatus() {
        $status = $this->getSystemStatus();
        
        echo "🖥️ 系统状态监控\n";
        echo "==================\n";
        echo "时间: {$status['timestamp']}\n\n";
        
        // CPU状态
        $cpu = $status['cpu'];
        $cpuIcon = $cpu['status'] === 'high' ? '🔴' : ($cpu['status'] === 'medium' ? '🟡' : '🟢');
        echo "{$cpuIcon} CPU负载: {$cpu['load_1min']} (1分钟) | {$cpu['load_5min']} (5分钟) | {$cpu['load_15min']} (15分钟)\n";
        
        // 内存状态
        $memory = $status['memory'];
        $memoryIcon = $memory['status'] === 'high' ? '🔴' : ($memory['status'] === 'medium' ? '🟡' : '🟢');
        echo "{$memoryIcon} 内存使用: {$memory['usage_formatted']} / {$memory['limit_formatted']} ({$memory['usage_percent']}%)\n";
        
        // 磁盘状态
        $disk = $status['disk'];
        $diskIcon = $disk['status'] === 'high' ? '🔴' : ($disk['status'] === 'medium' ? '🟡' : '🟢');
        echo "{$diskIcon} 磁盘使用: {$disk['used_formatted']} / {$disk['total_formatted']} ({$disk['usage_percent']}%)\n";
        
        // FFmpeg状态
        $ffmpeg = $status['ffmpeg'];
        $ffmpegIcon = $ffmpeg['status'] === 'max' ? '🔴' : '🟢';
        echo "{$ffmpegIcon} FFmpeg进程: {$ffmpeg['current']}/{$ffmpeg['max']}\n";
        
        // 数据库状态
        $db = $status['database'];
        $dbIcon = $db['status'] === 'connected' ? '🟢' : '🔴';
        echo "{$dbIcon} 数据库: {$db['status']}\n";
        
        // 任务状态
        $tasks = $status['tasks'];
        if (isset($tasks['error'])) {
            echo "❌ 任务状态: 错误 - {$tasks['error']}\n";
        } else {
            echo "📋 任务状态: 待处理({$tasks['pending']}) 处理中({$tasks['processing']}) 完成({$tasks['completed']}) 失败({$tasks['failed']})\n";
        }
        
        echo "\n";
    }
}

// 如果直接运行此文件，显示系统状态
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    require_once 'config/database.php';
    
    $monitor = new SystemMonitor();
    $monitor->displayStatus();
}
?>
