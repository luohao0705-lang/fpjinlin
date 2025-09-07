<?php
/**
 * 视频处理类
 * 复盘精灵系统 - FFmpeg视频处理
 */

class VideoProcessor {
    private $db;
    private $ossClient;
    private $config;
    
    public function __construct() {
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
        
        $this->loadConfig();
        $this->initOssClient();
    }
    
    /**
     * 加载配置
     */
    private function loadConfig() {
        $this->config = [
            'max_duration' => getSystemConfig('max_video_duration', 3600),
            'segment_duration' => getSystemConfig('video_segment_duration', 120),
            'resolution' => getSystemConfig('video_resolution', '720p'),
            'video_bitrate' => getSystemConfig('video_bitrate', '1500k'),
            'audio_bitrate' => getSystemConfig('audio_bitrate', '64k'),
            'oss_bucket' => getSystemConfig('oss_bucket', ''),
            'oss_endpoint' => getSystemConfig('oss_endpoint', ''),
            'oss_access_key' => getSystemConfig('oss_access_key', ''),
            'oss_secret_key' => getSystemConfig('oss_secret_key', ''),
        ];
    }
    
    /**
     * 初始化阿里云OSS客户端
     */
    private function initOssClient() {
        if (empty($this->config['oss_bucket'])) {
            throw new Exception('OSS配置未完成');
        }
        
        // 这里需要引入阿里云OSS SDK
        // require_once 'vendor/autoload.php';
        // $this->ossClient = new \OSS\OssClient($this->config['oss_access_key'], $this->config['oss_secret_key'], $this->config['oss_endpoint']);
    }
    
    /**
     * 下载视频文件
     */
    public function downloadVideo($videoFileId, $flvUrl) {
        try {
            error_log("开始下载视频: {$flvUrl}");
            
            // 更新状态为下载中
            $this->updateVideoFileStatus($videoFileId, 'downloading');
            
            // 生成临时文件名
            $tempFile = sys_get_temp_dir() . '/video_' . $videoFileId . '_' . time() . '.flv';
            
            // 下载文件
            $this->downloadFile($flvUrl, $tempFile);
            
            // 获取视频信息
            $videoInfo = $this->getVideoInfo($tempFile);
            
            // 检查时长限制
            if ($videoInfo['duration'] > $this->config['max_duration']) {
                $videoInfo['duration'] = $this->config['max_duration'];
            }
            
            // 上传到OSS
            $ossKey = $this->uploadToOss($tempFile, "videos/{$videoFileId}/original.flv");
            
            // 更新数据库
            $this->db->query(
                "UPDATE video_files SET oss_key = ?, file_size = ?, duration = ?, resolution = ?, status = 'completed' WHERE id = ?",
                [$ossKey, filesize($tempFile), $videoInfo['duration'], $videoInfo['resolution'], $videoFileId]
            );
            
            // 清理临时文件
            unlink($tempFile);
            
            error_log("视频下载完成: {$videoFileId}");
            return true;
            
        } catch (Exception $e) {
            error_log("视频下载失败: {$videoFileId} - " . $e->getMessage());
            $this->updateVideoFileStatus($videoFileId, 'failed', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 转码视频
     */
    public function transcodeVideo($videoFileId) {
        try {
            error_log("开始转码视频: {$videoFileId}");
            
            // 获取视频文件信息
            $videoFile = $this->db->fetchOne("SELECT * FROM video_files WHERE id = ?", [$videoFileId]);
            if (!$videoFile || !$videoFile['oss_key']) {
                throw new Exception('视频文件不存在或未下载完成');
            }
            
            // 从OSS下载到临时文件
            $tempInputFile = $this->downloadFromOss($videoFile['oss_key']);
            $tempOutputFile = sys_get_temp_dir() . '/transcoded_' . $videoFileId . '_' . time() . '.mp4';
            
            // FFmpeg转码命令
            $ffmpegCmd = $this->buildTranscodeCommand($tempInputFile, $tempOutputFile, $videoFile['duration']);
            
            // 执行转码
            $this->executeFFmpeg($ffmpegCmd);
            
            // 上传转码后的文件到OSS
            $ossKey = $this->uploadToOss($tempOutputFile, "videos/{$videoFileId}/transcoded.mp4");
            
            // 更新数据库
            $this->db->query(
                "UPDATE video_files SET oss_key = ?, file_size = ?, resolution = ?, status = 'completed' WHERE id = ?",
                [$ossKey, filesize($tempOutputFile), $this->config['resolution'], $videoFileId]
            );
            
            // 清理临时文件
            unlink($tempInputFile);
            unlink($tempOutputFile);
            
            error_log("视频转码完成: {$videoFileId}");
            return true;
            
        } catch (Exception $e) {
            error_log("视频转码失败: {$videoFileId} - " . $e->getMessage());
            $this->updateVideoFileStatus($videoFileId, 'failed', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 视频切片
     */
    public function segmentVideo($videoFileId) {
        try {
            error_log("开始视频切片: {$videoFileId}");
            
            // 获取视频文件信息
            $videoFile = $this->db->fetchOne("SELECT * FROM video_files WHERE id = ?", [$videoFileId]);
            if (!$videoFile || !$videoFile['oss_key']) {
                throw new Exception('视频文件不存在或未转码完成');
            }
            
            $duration = min($videoFile['duration'], $this->config['max_duration']);
            $segmentDuration = $this->config['segment_duration'];
            $segmentCount = ceil($duration / $segmentDuration);
            
            // 从OSS下载到临时文件
            $tempInputFile = $this->downloadFromOss($videoFile['oss_key']);
            
            // 创建切片
            for ($i = 0; $i < $segmentCount; $i++) {
                $startTime = $i * $segmentDuration;
                $endTime = min(($i + 1) * $segmentDuration, $duration);
                $segmentDuration = $endTime - $startTime;
                
                $tempSegmentFile = sys_get_temp_dir() . '/segment_' . $videoFileId . '_' . $i . '.mp4';
                
                // FFmpeg切片命令
                $ffmpegCmd = $this->buildSegmentCommand($tempInputFile, $tempSegmentFile, $startTime, $segmentDuration);
                
                // 执行切片
                $this->executeFFmpeg($ffmpegCmd);
                
                // 上传切片到OSS
                $ossKey = $this->uploadToOss($tempSegmentFile, "videos/{$videoFileId}/segments/segment_{$i}.mp4");
                
                // 保存切片记录
                $this->db->insert(
                    "INSERT INTO video_segments (video_file_id, segment_index, start_time, end_time, duration, oss_key, file_size, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())",
                    [$videoFileId, $i, $startTime, $endTime, $segmentDuration, $ossKey, filesize($tempSegmentFile)]
                );
                
                // 清理临时切片文件
                unlink($tempSegmentFile);
            }
            
            // 清理临时输入文件
            unlink($tempInputFile);
            
            error_log("视频切片完成: {$videoFileId}, 共{$segmentCount}个切片");
            return true;
            
        } catch (Exception $e) {
            error_log("视频切片失败: {$videoFileId} - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 构建转码命令
     */
    private function buildTranscodeCommand($inputFile, $outputFile, $duration) {
        $maxDuration = min($duration, $this->config['max_duration']);
        
        return sprintf(
            'ffmpeg -i %s -t %d -c:v libx264 -preset fast -crf 23 -maxrate %s -bufsize %s -c:a aac -b:a %s -ac 2 -ar 44100 -movflags +faststart %s -y',
            escapeshellarg($inputFile),
            $maxDuration,
            $this->config['video_bitrate'],
            $this->config['video_bitrate'],
            $this->config['audio_bitrate'],
            escapeshellarg($outputFile)
        );
    }
    
    /**
     * 构建切片命令
     */
    private function buildSegmentCommand($inputFile, $outputFile, $startTime, $duration) {
        return sprintf(
            'ffmpeg -i %s -ss %d -t %d -c copy -avoid_negative_ts make_zero %s -y',
            escapeshellarg($inputFile),
            $startTime,
            $duration,
            escapeshellarg($outputFile)
        );
    }
    
    /**
     * 执行FFmpeg命令
     */
    private function executeFFmpeg($command) {
        $output = [];
        $returnCode = 0;
        
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception('FFmpeg执行失败: ' . implode("\n", $output));
        }
        
        return $output;
    }
    
    /**
     * 获取视频信息
     */
    private function getVideoInfo($filePath) {
        $command = sprintf(
            'ffprobe -v quiet -print_format json -show_format -show_streams %s',
            escapeshellarg($filePath)
        );
        
        $output = [];
        exec($command, $output);
        $info = json_decode(implode('', $output), true);
        
        if (!$info) {
            throw new Exception('无法获取视频信息');
        }
        
        $videoStream = null;
        foreach ($info['streams'] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $videoStream = $stream;
                break;
            }
        }
        
        if (!$videoStream) {
            throw new Exception('未找到视频流');
        }
        
        return [
            'duration' => (int)$info['format']['duration'],
            'resolution' => $videoStream['width'] . 'x' . $videoStream['height'],
            'bitrate' => (int)$info['format']['bit_rate'],
            'codec' => $videoStream['codec_name']
        ];
    }
    
    /**
     * 下载文件
     */
    private function downloadFile($url, $filePath) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 300,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $fileContent = file_get_contents($url, false, $context);
        if ($fileContent === false) {
            throw new Exception('下载文件失败');
        }
        
        if (file_put_contents($filePath, $fileContent) === false) {
            throw new Exception('保存文件失败');
        }
    }
    
    /**
     * 上传到OSS
     */
    private function uploadToOss($filePath, $ossKey) {
        // 这里需要实现OSS上传逻辑
        // $this->ossClient->uploadFile($this->config['oss_bucket'], $ossKey, $filePath);
        
        // 临时实现：直接返回文件路径
        return $ossKey;
    }
    
    /**
     * 从OSS下载
     */
    private function downloadFromOss($ossKey) {
        // 这里需要实现OSS下载逻辑
        // $tempFile = sys_get_temp_dir() . '/temp_' . time() . '.mp4';
        // $this->ossClient->getObject($this->config['oss_bucket'], $ossKey, $tempFile);
        // return $tempFile;
        
        // 临时实现：直接返回OSS键
        return $ossKey;
    }
    
    /**
     * 更新视频文件状态
     */
    private function updateVideoFileStatus($videoFileId, $status, $errorMessage = null) {
        $sql = "UPDATE video_files SET status = ?";
        $params = [$status];
        
        if ($errorMessage) {
            $sql .= ", error_message = ?";
            $params[] = $errorMessage;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $videoFileId;
        
        $this->db->query($sql, $params);
    }
}
