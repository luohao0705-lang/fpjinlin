<?php
/**
 * 轻量级视频录制器
 * 使用多种技术降低CPU占用
 */

class LightweightVideoRecorder {
    
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = new Database();
        $this->config = $this->getSystemConfig();
    }
    
    /**
     * 智能录制视频 - 自动选择最佳方案
     */
    public function recordVideo($videoFileId, $flvUrl, $maxDuration = 3600) {
        try {
            // 1. 验证FLV地址
            if (!$this->validateFlvUrl($flvUrl)) {
                throw new Exception("FLV地址无效或已过期");
            }
            
            // 2. 选择最佳录制方案
            $method = $this->selectBestMethod($flvUrl);
            
            // 3. 执行录制
            $result = $this->executeRecording($method, $flvUrl, $maxDuration);
            
            // 4. 更新数据库
            $this->updateVideoFile($videoFileId, $result);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logError($videoFileId, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 方案1：wget下载 + 后处理（最轻量）
     */
    private function recordWithWget($flvUrl, $maxDuration) {
        $tempFile = sys_get_temp_dir() . '/video_' . time() . '.flv';
        $outputFile = sys_get_temp_dir() . '/video_' . time() . '.mp4';
        
        // 1. 使用wget下载
        $command = sprintf(
            'wget --user-agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" ' .
            '--header="Referer: https://live.douyin.com/" ' .
            '--timeout=30 --tries=3 --continue -O %s %s 2>&1',
            escapeshellarg($tempFile),
            escapeshellarg($flvUrl)
        );
        
        $this->logInfo("使用wget下载: $command");
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($tempFile)) {
            throw new Exception("wget下载失败: " . implode("\n", $output));
        }
        
        // 2. 检查文件大小
        $fileSize = filesize($tempFile);
        if ($fileSize < 1024 * 1024) { // 小于1MB
            throw new Exception("下载的文件太小，可能已过期");
        }
        
        // 3. 轻量级后处理
        $this->processWithFFmpeg($tempFile, $outputFile, $maxDuration);
        
        // 4. 清理临时文件
        unlink($tempFile);
        
        return $outputFile;
    }
    
    /**
     * 方案2：yt-dlp下载（专业工具）
     */
    private function recordWithYtDlp($flvUrl, $maxDuration) {
        $outputFile = sys_get_temp_dir() . '/video_' . time() . '.%(ext)s';
        
        $command = sprintf(
            'yt-dlp --format best --output %s --max-downloads 1 --no-playlist %s 2>&1',
            escapeshellarg($outputFile),
            escapeshellarg($flvUrl)
        );
        
        $this->logInfo("使用yt-dlp下载: $command");
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("yt-dlp下载失败: " . implode("\n", $output));
        }
        
        // 查找实际生成的文件
        $pattern = sys_get_temp_dir() . '/video_*.*';
        $files = glob($pattern);
        if (empty($files)) {
            throw new Exception("yt-dlp未生成文件");
        }
        
        return $files[0];
    }
    
    /**
     * 方案3：FFmpeg copy模式（最轻量转码）
     */
    private function recordWithFFmpegCopy($flvUrl, $maxDuration) {
        $outputFile = sys_get_temp_dir() . '/video_' . time() . '.mp4';
        
        $command = sprintf(
            'ffmpeg -user_agent "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" ' .
            '-headers "Referer: https://live.douyin.com/" ' .
            '-i %s -t %d -c copy -avoid_negative_ts make_zero -fflags +genpts %s -y 2>&1',
            escapeshellarg($flvUrl),
            $maxDuration,
            escapeshellarg($outputFile)
        );
        
        $this->logInfo("使用FFmpeg copy模式: $command");
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($outputFile)) {
            throw new Exception("FFmpeg copy失败: " . implode("\n", $output));
        }
        
        return $outputFile;
    }
    
    /**
     * 方案4：分段下载（适合长直播）
     */
    private function recordWithSegments($flvUrl, $maxDuration) {
        $segmentDuration = 300; // 5分钟一段
        $segments = [];
        $outputFile = sys_get_temp_dir() . '/video_' . time() . '.mp4';
        
        for ($i = 0; $i < $maxDuration; $i += $segmentDuration) {
            $segmentFile = sys_get_temp_dir() . '/segment_' . $i . '.flv';
            $currentDuration = min($segmentDuration, $maxDuration - $i);
            
            $command = sprintf(
                'ffmpeg -ss %d -i %s -t %d -c copy %s -y 2>&1',
                $i,
                escapeshellarg($flvUrl),
                $currentDuration,
                escapeshellarg($segmentFile)
            );
            
            exec($command, $output, $returnCode);
            if ($returnCode === 0 && file_exists($segmentFile)) {
                $segments[] = $segmentFile;
            }
        }
        
        if (empty($segments)) {
            throw new Exception("分段下载失败");
        }
        
        // 合并分段
        $this->mergeSegments($segments, $outputFile);
        
        // 清理分段文件
        foreach ($segments as $segment) {
            unlink($segment);
        }
        
        return $outputFile;
    }
    
    /**
     * 选择最佳录制方案
     */
    private function selectBestMethod($flvUrl) {
        // 检查系统资源
        $cpuLoad = sys_getloadavg()[0];
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        // 检查工具可用性
        $wgetAvailable = $this->checkTool('wget');
        $ytDlpAvailable = $this->checkTool('yt-dlp');
        $ffmpegAvailable = $this->checkTool('ffmpeg');
        
        // 根据资源情况选择方案
        if ($cpuLoad < 0.5 && $wgetAvailable) {
            return 'wget'; // 最轻量
        } elseif ($ytDlpAvailable) {
            return 'yt-dlp'; // 专业工具
        } elseif ($ffmpegAvailable) {
            return 'ffmpeg_copy'; // FFmpeg copy模式
        } else {
            return 'segments'; // 分段下载
        }
    }
    
    /**
     * 执行录制
     */
    private function executeRecording($method, $flvUrl, $maxDuration) {
        switch ($method) {
            case 'wget':
                return $this->recordWithWget($flvUrl, $maxDuration);
            case 'yt-dlp':
                return $this->recordWithYtDlp($flvUrl, $maxDuration);
            case 'ffmpeg_copy':
                return $this->recordWithFFmpegCopy($flvUrl, $maxDuration);
            case 'segments':
                return $this->recordWithSegments($flvUrl, $maxDuration);
            default:
                throw new Exception("未知的录制方案: $method");
        }
    }
    
    /**
     * 轻量级FFmpeg处理
     */
    private function processWithFFmpeg($inputFile, $outputFile, $maxDuration) {
        $command = sprintf(
            'ffmpeg -i %s -t %d -c copy -avoid_negative_ts make_zero -fflags +genpts %s -y 2>&1',
            escapeshellarg($inputFile),
            $maxDuration,
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($outputFile)) {
            throw new Exception("FFmpeg处理失败: " . implode("\n", $output));
        }
    }
    
    /**
     * 合并视频分段
     */
    private function mergeSegments($segments, $outputFile) {
        $listFile = sys_get_temp_dir() . '/segments_list.txt';
        $content = '';
        
        foreach ($segments as $segment) {
            $content .= "file '" . $segment . "'\n";
        }
        
        file_put_contents($listFile, $content);
        
        $command = sprintf(
            'ffmpeg -f concat -safe 0 -i %s -c copy %s -y 2>&1',
            escapeshellarg($listFile),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $returnCode);
        unlink($listFile);
        
        if ($returnCode !== 0 || !file_exists($outputFile)) {
            throw new Exception("分段合并失败: " . implode("\n", $output));
        }
    }
    
    /**
     * 验证FLV地址
     */
    private function validateFlvUrl($flvUrl) {
        if (!filter_var($flvUrl, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // 检查是否是抖音链接
        if (strpos($flvUrl, 'douyin.com') === false) {
            return false;
        }
        
        // 检查URL是否包含过期时间
        if (strpos($flvUrl, 'expire=') === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查工具是否可用
     */
    private function checkTool($tool) {
        $output = [];
        exec("which $tool 2>/dev/null", $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * 更新视频文件记录
     */
    private function updateVideoFile($videoFileId, $filePath) {
        $this->db->update(
            'video_files',
            [
                'status' => 'completed',
                'file_path' => $filePath,
                'file_size' => filesize($filePath),
                'recording_completed_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $videoFileId]
        );
    }
    
    /**
     * 记录错误
     */
    private function logError($videoFileId, $message) {
        error_log("LightweightVideoRecorder Error [VideoFile:$videoFileId]: $message");
    }
    
    /**
     * 记录信息
     */
    private function logInfo($message) {
        error_log("LightweightVideoRecorder Info: $message");
    }
    
    /**
     * 获取系统配置
     */
    private function getSystemConfig() {
        $config = [];
        $result = $this->db->fetchAll("SELECT config_key, config_value FROM system_config");
        foreach ($result as $row) {
            $config[$row['config_key']] = $row['config_value'];
        }
        return $config;
    }
    
    /**
     * 解析内存限制
     */
    private function parseMemoryLimit($memoryLimit) {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit)-1]);
        $memoryLimit = (int) $memoryLimit;
        
        switch($last) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }
        
        return $memoryLimit;
    }
}
?>
