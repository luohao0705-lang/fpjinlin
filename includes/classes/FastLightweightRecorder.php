<?php
/**
 * 快速轻量级录制器
 * 使用wget下载 + FFmpeg轻量处理
 */

class FastLightweightRecorder {
    
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = new Database();
        $this->config = $this->getSystemConfig();
    }
    
    /**
     * 快速录制视频
     */
    public function recordVideo($videoFileId, $flvUrl, $maxDuration = 3600) {
        try {
            error_log("🚀 开始快速录制: {$flvUrl}");
            
            // 1. 验证FLV地址
            if (!$this->validateFlvUrl($flvUrl)) {
                throw new Exception("FLV地址无效或已过期");
            }
            
            // 2. 生成临时文件路径
            $tempDir = sys_get_temp_dir() . '/video_recording_' . $videoFileId;
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $flvFile = $tempDir . '/video.flv';
            $mp4File = $tempDir . '/video.mp4';
            
            // 3. 使用wget下载FLV流
            $this->downloadWithWget($flvUrl, $flvFile, $maxDuration);
            
            // 4. 轻量级FFmpeg处理
            $this->processWithFFmpeg($flvFile, $mp4File, $maxDuration);
            
            // 5. 验证文件
            $this->validateFile($mp4File);
            
            // 6. 清理临时文件
            unlink($flvFile);
            rmdir($tempDir);
            
            error_log("✅ 快速录制完成: {$mp4File}");
            
            return [
                'file_path' => $mp4File,
                'file_size' => filesize($mp4File),
                'duration' => $this->getVideoDuration($mp4File)
            ];
            
        } catch (Exception $e) {
            error_log("❌ 快速录制失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 使用wget下载FLV流
     */
    private function downloadWithWget($flvUrl, $outputFile, $maxDuration) {
        // 计算超时时间（最大录制时长 + 30秒缓冲）
        $timeout = $maxDuration + 30;
        
        // 构建wget命令
        $command = sprintf(
            'wget --user-agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" ' .
            '--header="Referer: https://live.douyin.com/" ' .
            '--timeout=%d --tries=3 --continue --no-check-certificate ' .
            '--output-document=%s %s 2>&1',
            $timeout,
            escapeshellarg($outputFile),
            escapeshellarg($flvUrl)
        );
        
        error_log("📥 执行wget下载: $command");
        
        $output = [];
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("wget下载失败 (返回码: $returnCode): " . implode("\n", $output));
        }
        
        if (!file_exists($outputFile) || filesize($outputFile) < 1024) {
            throw new Exception("下载的文件无效或太小");
        }
        
        error_log("✅ wget下载完成，文件大小: " . filesize($outputFile) . " bytes");
    }
    
    /**
     * 轻量级FFmpeg处理
     */
    private function processWithFFmpeg($inputFile, $outputFile, $maxDuration) {
        // 使用copy模式，不转码，只处理时间戳
        $command = sprintf(
            'ffmpeg -i %s -t %d -c copy -avoid_negative_ts make_zero -fflags +genpts %s -y 2>&1',
            escapeshellarg($inputFile),
            $maxDuration,
            escapeshellarg($outputFile)
        );
        
        error_log("🎬 执行FFmpeg处理: $command");
        
        $output = [];
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("FFmpeg处理失败 (返回码: $returnCode): " . implode("\n", $output));
        }
        
        if (!file_exists($outputFile) || filesize($outputFile) < 1024) {
            throw new Exception("FFmpeg处理后的文件无效");
        }
        
        error_log("✅ FFmpeg处理完成，文件大小: " . filesize($outputFile) . " bytes");
    }
    
    /**
     * 验证文件
     */
    private function validateFile($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("文件不存在");
        }
        
        $fileSize = filesize($filePath);
        if ($fileSize < 1024 * 1024) { // 小于1MB
            throw new Exception("文件太小，可能录制失败");
        }
        
        // 检查文件头
        $handle = fopen($filePath, 'rb');
        $header = fread($handle, 8);
        fclose($handle);
        
        // 检查是否是有效的视频文件
        if (strpos($header, 'ftyp') === false && strpos($header, 'moov') === false) {
            throw new Exception("文件格式无效");
        }
    }
    
    /**
     * 获取视频时长
     */
    private function getVideoDuration($filePath) {
        $command = sprintf(
            'ffprobe -v quiet -show_entries format=duration -of csv="p=0" %s 2>/dev/null',
            escapeshellarg($filePath)
        );
        
        $duration = trim(shell_exec($command));
        return $duration ? (float)$duration : 0;
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
    public function checkTool($tool) {
        $output = [];
        exec("which $tool 2>/dev/null", $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * 获取系统配置
     */
    private function getSystemConfig() {
        $config = [];
        try {
            $result = $this->db->fetchAll("SELECT config_key, config_value FROM system_config");
            foreach ($result as $row) {
                $config[$row['config_key']] = $row['config_value'];
            }
        } catch (Exception $e) {
            error_log("获取系统配置失败: " . $e->getMessage());
        }
        return $config;
    }
}
?>
