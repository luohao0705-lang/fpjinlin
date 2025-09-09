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
     * 获取配置信息
     */
    public function getConfig() {
        return $this->config;
    }
    
    /**
     * 初始化阿里云OSS客户端
     */
    private function initOssClient() {
        // 如果OSS配置不完整，使用本地存储模式
        if (empty($this->config['oss_bucket'])) {
            error_log("⚠️ OSS配置不完整，将使用本地存储模式");
            $this->ossClient = null;
            return;
        }
        
        // 这里需要引入阿里云OSS SDK
        // require_once 'vendor/autoload.php';
        // $this->ossClient = new \OSS\OssClient($this->config['oss_access_key'], $this->config['oss_secret_key'], $this->config['oss_endpoint']);
    }
    
    /**
     * 录制FLV流视频
     */
    public function recordVideo($videoFileId, $flvUrl) {
        try {
            error_log("🎬 开始录制视频: {$flvUrl}");
            
            // 更新状态为录制中
            $this->updateVideoFileStatus($videoFileId, 'recording');
            $this->updateRecordingProgress($videoFileId, 0, '开始录制', 'recording');
            
            // 检查FFmpeg是否可用
            if (!$this->checkFFmpeg()) {
                throw new Exception('FFmpeg未安装或不可用');
            }
            
            $this->updateRecordingProgress($videoFileId, 10, '检查FFmpeg环境', 'recording');
            
            // 检查FLV地址是否可访问
            if (!$this->checkFlvUrl($flvUrl)) {
                throw new Exception('FLV地址不可访问: ' . $flvUrl);
            }
            
            $this->updateRecordingProgress($videoFileId, 20, '验证FLV地址', 'recording');
            
            // 生成临时文件名
            $tempFile = sys_get_temp_dir() . '/video_' . $videoFileId . '_' . time() . '.mp4';
            
            $this->updateRecordingProgress($videoFileId, 30, '准备录制环境', 'recording');
            
            // 使用FFmpeg录制FLV流（带进度监控）
            $this->recordFlvStreamWithProgress($flvUrl, $tempFile, $videoFileId);
            
            // 确保录制完成后更新进度
            $this->updateRecordingProgress($videoFileId, 80, '录制完成，处理文件', 'recording');
            
            // 检查录制文件
            if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                throw new Exception('录制文件生成失败');
            }
            
            // 获取视频信息
            $videoInfo = $this->getVideoInfo($tempFile);
            
            // 检查时长限制
            if ($videoInfo['duration'] > $this->config['max_duration']) {
                $videoInfo['duration'] = $this->config['max_duration'];
            }
            
            $this->updateRecordingProgress($videoFileId, 90, '上传到存储', 'recording');
            
            // 上传到OSS
            $ossKey = $this->uploadToOss($tempFile, "videos/{$videoFileId}/original.mp4");
            
            // 更新数据库
            $this->db->query(
                "UPDATE video_files SET oss_key = ?, file_size = ?, duration = ?, resolution = ?, status = 'completed', recording_progress = 100, recording_status = 'completed', recording_completed_at = NOW() WHERE id = ?",
                [$ossKey, filesize($tempFile), $videoInfo['duration'], $videoInfo['resolution'], $videoFileId]
            );
            
            // 清理临时文件
            unlink($tempFile);
            
            $this->updateRecordingProgress($videoFileId, 100, '录制完成', 'completed');
            
            error_log("✅ 视频录制完成: {$videoFileId}, 时长: {$videoInfo['duration']}秒, 分辨率: {$videoInfo['resolution']}");
            return true;
            
        } catch (Exception $e) {
            error_log("❌ 视频录制失败: {$videoFileId} - " . $e->getMessage());
            $this->updateVideoFileStatus($videoFileId, 'failed', $e->getMessage());
            $this->updateRecordingProgress($videoFileId, 0, '录制失败: ' . $e->getMessage(), 'failed');
            throw $e;
        }
    }
    
    /**
     * 下载视频文件（保留原方法用于兼容）
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
            
            error_log("📊 切片配置 - 视频时长: {$duration}秒, 切片时长: {$segmentDuration}秒, 切片数量: {$segmentCount}");
            
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
        require_once 'StorageManager.php';
        $storageManager = new StorageManager();
        return $storageManager->upload($filePath, $ossKey);
    }
    
    /**
     * 从OSS下载
     */
    private function downloadFromOss($ossKey) {
        require_once 'StorageManager.php';
        $storageManager = new StorageManager();
        return $storageManager->download($ossKey);
    }
    
    /**
     * 更新视频文件状态
     */
    private function updateVideoFileStatus($videoFileId, $status, $errorMessage = null) {
        // 映射状态值到数据库支持的值
        $statusMap = [
            'recording' => 'processing',
            'downloading' => 'processing',
            'completed' => 'completed',
            'failed' => 'failed',
            'pending' => 'pending'
        ];
        
        $dbStatus = $statusMap[$status] ?? 'pending';
        
        $sql = "UPDATE video_files SET status = ?";
        $params = [$dbStatus];
        
        if ($errorMessage) {
            $sql .= ", error_message = ?";
            $params[] = $errorMessage;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $videoFileId;
        
        $this->db->query($sql, $params);
    }
    
    /**
     * 检查FFmpeg是否可用
     */
    private function checkFFmpeg() {
        $output = [];
        $returnCode = 0;
        $ffmpegFound = false;
        $ffmpegPath = '';
        
        // 尝试不同的FFmpeg命令（支持Linux和Windows）
        $ffmpegCommands = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/ffmpeg/bin/ffmpeg', 'ffmpeg.exe'];
        
        foreach ($ffmpegCommands as $cmd) {
            exec($cmd . ' -version 2>&1', $output, $returnCode);
            if ($returnCode === 0) {
                $ffmpegFound = true;
                $ffmpegPath = $cmd;
                break;
            }
        }
        
        if (!$ffmpegFound) {
            error_log("❌ FFmpeg未找到，尝试的命令: " . implode(', ', $ffmpegCommands));
            error_log("❌ 最后执行的命令返回码: {$returnCode}");
            if (!empty($output)) {
                error_log("❌ 最后执行的命令输出: " . implode("\n", $output));
            }
        } else {
            error_log("✅ FFmpeg找到: {$ffmpegPath}");
        }
        
        return $ffmpegFound;
    }
    
    /**
     * 检查FLV地址是否可访问
     */
    private function checkFlvUrl($flvUrl) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'HEAD',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $headers = @get_headers($flvUrl, 1, $context);
        if ($headers) {
            $statusCode = $headers[0];
            // 接受200、302、403等状态码
            if (strpos($statusCode, '200') !== false || 
                strpos($statusCode, '302') !== false || 
                strpos($statusCode, '403') !== false) {
                return true;
            }
        }
        
        // 如果HEAD请求失败，尝试GET请求（只获取少量数据）
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $testData = @file_get_contents($flvUrl, false, $context, 0, 1024);
        return $testData !== false && strlen($testData) > 0;
    }
    
    /**
     * 录制FLV流
     */
    private function recordFlvStream($flvUrl, $outputFile) {
        $maxDuration = $this->config['max_duration'];
        
        // 判断输入是FLV流还是本地文件
        $isLocalFile = file_exists($flvUrl) || strpos($flvUrl, 'http') !== 0;
        
        if ($isLocalFile) {
            // 本地文件，使用简单参数
            $command = sprintf(
                'ffmpeg -i %s -t %d -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart %s -y',
                escapeshellarg($flvUrl),
                $maxDuration,
                escapeshellarg($outputFile)
            );
        } else {
            // FLV流，使用流优化参数
            $command = sprintf(
                'ffmpeg -user_agent "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" -headers "Referer: https://live.douyin.com/" -i %s -t %d -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart -avoid_negative_ts make_zero -fflags +genpts %s -y',
                escapeshellarg($flvUrl),
                $maxDuration,
                escapeshellarg($outputFile)
            );
        }
        
        error_log("🔧 执行FFmpeg命令: {$command}");
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            error_log("❌ FFmpeg录制失败: {$errorMsg}");
            throw new Exception('FFmpeg录制失败: ' . $errorMsg);
        }
        
        if (!file_exists($outputFile) || filesize($outputFile) === 0) {
            throw new Exception('录制文件生成失败');
        }
        
        error_log("✅ FFmpeg录制成功: {$outputFile}");
    }
    
    /**
     * 录制FLV流（带进度监控）
     */
    private function recordFlvStreamWithProgress($flvUrl, $outputFile, $videoFileId) {
        $maxDuration = $this->config['max_duration'];
        
        // 判断输入是FLV流还是本地文件
        $isLocalFile = file_exists($flvUrl) || strpos($flvUrl, 'http') !== 0;
        
        if ($isLocalFile) {
            // 本地文件，使用简单参数
            $command = sprintf(
                'ffmpeg -i %s -t %d -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart %s -y -progress -',
                escapeshellarg($flvUrl),
                $maxDuration,
                escapeshellarg($outputFile)
            );
        } else {
            // FLV流，使用流优化参数
            $command = sprintf(
                'ffmpeg -user_agent "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" -headers "Referer: https://live.douyin.com/" -i %s -t %d -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart -avoid_negative_ts make_zero -fflags +genpts %s -y -progress -',
                escapeshellarg($flvUrl),
                $maxDuration,
                escapeshellarg($outputFile)
            );
        }
        
        error_log("🔧 执行FFmpeg命令: {$command}");
        error_log("📊 配置参数 - 最大录制时长: {$maxDuration}秒");
        
        // 使用proc_open来实时监控进度
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr
        );
        
        // 检查proc_open是否可用
        if (!function_exists('proc_open')) {
            error_log("⚠️ proc_open不可用，使用exec作为备选方案");
            $this->updateRecordingProgress($videoFileId, 40, "开始录制...", 'recording');
            $this->recordFlvStream($flvUrl, $outputFile);
            $this->updateRecordingProgress($videoFileId, 80, "录制完成", 'recording');
            return;
        }
        
        $process = proc_open($command, $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            fclose($pipes[0]); // 关闭stdin
            
            $startTime = time();
            $lastProgress = 30;
            
            while (($line = fgets($pipes[1])) !== false) {
                // 解析FFmpeg进度输出
                if (strpos($line, 'out_time_ms=') !== false) {
                    preg_match('/out_time_ms=(\d+)/', $line, $matches);
                    if (isset($matches[1])) {
                        $currentTime = intval($matches[1]) / 1000000; // 转换为秒
                        $progress = min(30 + intval(($currentTime / $maxDuration) * 50), 80);
                        
                        if ($progress > $lastProgress) {
                            $this->updateRecordingProgress($videoFileId, $progress, "录制中... {$currentTime}s", 'recording');
                            $lastProgress = $progress;
                        }
                    }
                }
                
                if (strpos($line, 'size=') !== false) {
                    preg_match('/size=(\d+)/', $line, $matches);
                    if (isset($matches[1])) {
                        $fileSize = intval($matches[1]);
                        $this->logRecordingProgress($videoFileId, $lastProgress, "录制中... 文件大小: " . $this->formatFileSize($fileSize), intval($currentTime ?? 0), $fileSize);
                    }
                }
            }
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $returnCode = proc_close($process);
            
            if ($returnCode !== 0) {
                throw new Exception('FFmpeg录制失败，返回码: ' . $returnCode);
            }
            
            // 更新最终进度
            $this->updateRecordingProgress($videoFileId, 80, "录制完成", 'recording');
        } else {
            // 如果proc_open不可用，回退到普通录制
            $this->updateRecordingProgress($videoFileId, 40, "开始录制...", 'recording');
            $this->recordFlvStream($flvUrl, $outputFile);
            $this->updateRecordingProgress($videoFileId, 80, "录制完成", 'recording');
        }
        
        if (!file_exists($outputFile) || filesize($outputFile) === 0) {
            throw new Exception('录制文件生成失败');
        }
        
        error_log("✅ FFmpeg录制成功: {$outputFile}");
    }
    
    /**
     * 更新录制进度
     */
    private function updateRecordingProgress($videoFileId, $progress, $message, $status) {
        try {
            // 更新video_files表
            $this->db->query(
                "UPDATE video_files SET recording_progress = ?, recording_status = ? WHERE id = ?",
                [$progress, $status, $videoFileId]
            );
            
            // 记录进度日志
            $this->logRecordingProgress($videoFileId, $progress, $message);
            
            error_log("📊 录制进度更新: 文件ID {$videoFileId}, 进度 {$progress}%, 状态: {$message}");
        } catch (Exception $e) {
            error_log("❌ 更新录制进度失败: " . $e->getMessage());
        }
    }
    
    /**
     * 记录录制进度日志
     */
    private function logRecordingProgress($videoFileId, $progress, $message, $duration = null, $fileSize = null) {
        try {
            $this->db->query(
                "INSERT INTO recording_progress_logs (video_file_id, progress, message, duration, file_size, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [$videoFileId, $progress, $message, $duration, $fileSize]
            );
        } catch (Exception $e) {
            error_log("❌ 记录进度日志失败: " . $e->getMessage());
        }
    }
    
    /**
     * 格式化文件大小
     */
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
