<?php
/**
 * Whisper语音识别服务类
 * 复盘精灵系统 - 开源语音识别
 */

class WhisperService {
    private $db;
    private $config;
    private $modelPath;
    
    public function __construct() {
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
        
        $this->loadConfig();
    }
    
    /**
     * 加载配置
     */
    private function loadConfig() {
        $this->modelPath = getSystemConfig('whisper_model_path', '/opt/whisper/models');
        $this->config = [
            'model_path' => $this->modelPath,
            'model_name' => 'base', // 可选: tiny, base, small, medium, large
            'language' => 'zh', // 中文识别
            'task' => 'transcribe',
            'output_format' => 'json'
        ];
    }
    
    /**
     * 处理视频切片的语音识别
     */
    public function processSegment($segmentId) {
        try {
            error_log("开始语音识别: 切片ID {$segmentId}");
            
            // 获取切片信息
            $segment = $this->db->fetchOne(
                "SELECT vs.*, vf.order_id FROM video_segments vs 
                 LEFT JOIN video_files vf ON vs.video_file_id = vf.id 
                 WHERE vs.id = ?",
                [$segmentId]
            );
            
            if (!$segment || !$segment['oss_key']) {
                throw new Exception('切片不存在或文件未准备好');
            }
            
            // 从OSS下载音频文件
            $audioFile = $this->extractAudioFromVideo($segment);
            
            // 执行Whisper识别
            $transcriptResult = $this->transcribeAudio($audioFile);
            
            // 保存识别结果
            $this->saveTranscriptResults($segmentId, $transcriptResult);
            
            // 清理临时文件
            if (file_exists($audioFile)) {
                unlink($audioFile);
            }
            
            error_log("语音识别完成: 切片ID {$segmentId}");
            return true;
            
        } catch (Exception $e) {
            error_log("语音识别失败: 切片ID {$segmentId} - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 从视频中提取音频
     */
    private function extractAudioFromVideo($segment) {
        $videoFile = $this->downloadFromOss($segment['oss_key']);
        $audioFile = sys_get_temp_dir() . '/audio_' . $segment['id'] . '_' . time() . '.wav';
        
        // FFmpeg提取音频命令
        $command = sprintf(
            'ffmpeg -i %s -vn -acodec pcm_s16le -ar 16000 -ac 1 %s -y',
            escapeshellarg($videoFile),
            escapeshellarg($audioFile)
        );
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception('音频提取失败: ' . implode("\n", $output));
        }
        
        // 清理视频文件
        if (file_exists($videoFile)) {
            unlink($videoFile);
        }
        
        return $audioFile;
    }
    
    /**
     * 执行Whisper语音识别
     */
    private function transcribeAudio($audioFile) {
        // 构建Whisper命令
        $command = sprintf(
            'whisper %s --model %s --language %s --task %s --output_format %s --output_dir %s',
            escapeshellarg($audioFile),
            $this->config['model_name'],
            $this->config['language'],
            $this->config['task'],
            $this->config['output_format'],
            escapeshellarg(dirname($audioFile))
        );
        
        error_log("执行Whisper命令: {$command}");
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception('Whisper识别失败: ' . implode("\n", $output));
        }
        
        // 读取JSON结果文件
        $jsonFile = str_replace('.wav', '.json', $audioFile);
        if (!file_exists($jsonFile)) {
            throw new Exception('Whisper结果文件不存在');
        }
        
        $result = json_decode(file_get_contents($jsonFile), true);
        if (!$result) {
            throw new Exception('Whisper结果解析失败');
        }
        
        // 清理结果文件
        unlink($jsonFile);
        
        return $result;
    }
    
    /**
     * 获取订单的所有转录文本
     * @param int $orderId 订单ID
     * @return array 转录文本数组
     */
    public function getOrderTranscripts($orderId) {
        $transcripts = $this->db->fetchAll(
            "SELECT vt.transcript_text FROM video_transcripts vt JOIN video_files vf ON vt.video_file_id = vf.id WHERE vf.order_id = ? ORDER BY vf.video_index, vt.segment_index",
            [$orderId]
        );
        return array_column($transcripts, 'transcript_text');
    }
    
    /**
     * 保存识别结果到数据库
     */
    private function saveTranscriptResults($segmentId, $transcriptResult) {
        if (!isset($transcriptResult['segments']) || !is_array($transcriptResult['segments'])) {
            throw new Exception('Whisper结果格式错误');
        }
        
        foreach ($transcriptResult['segments'] as $segment) {
            $this->db->insert(
                "INSERT INTO video_transcripts (segment_id, start_time, end_time, text, confidence, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    $segmentId,
                    $segment['start'],
                    $segment['end'],
                    $segment['text'],
                    $segment['avg_logprob'] ?? 0.0
                ]
            );
        }
    }
    
    /**
     * 获取订单的完整字幕
     */
    public function getOrderTranscripts($orderId) {
        $transcripts = $this->db->fetchAll(
            "SELECT vt.*, vs.segment_index, vs.start_time as segment_start, vs.end_time as segment_end,
                    vf.video_type, vf.video_index
             FROM video_transcripts vt
             LEFT JOIN video_segments vs ON vt.segment_id = vs.id
             LEFT JOIN video_files vf ON vs.video_file_id = vf.id
             WHERE vf.order_id = ?
             ORDER BY vf.video_type, vf.video_index, vs.segment_index, vt.start_time",
            [$orderId]
        );
        
        return $transcripts;
    }
    
    /**
     * 生成完整字幕文本
     */
    public function generateFullTranscript($orderId) {
        $transcripts = $this->getOrderTranscripts($orderId);
        
        $groupedTranscripts = [];
        foreach ($transcripts as $transcript) {
            $key = $transcript['video_type'] . '_' . $transcript['video_index'];
            if (!isset($groupedTranscripts[$key])) {
                $groupedTranscripts[$key] = [
                    'video_type' => $transcript['video_type'],
                    'video_index' => $transcript['video_index'],
                    'segments' => []
                ];
            }
            
            $groupedTranscripts[$key]['segments'][] = [
                'start_time' => $transcript['start_time'],
                'end_time' => $transcript['end_time'],
                'text' => $transcript['text'],
                'confidence' => $transcript['confidence']
            ];
        }
        
        return $groupedTranscripts;
    }
    
    /**
     * 从OSS下载文件
     */
    private function downloadFromOss($ossKey) {
        // 这里需要实现OSS下载逻辑
        // 临时实现：直接返回OSS键
        return $ossKey;
    }
    
    /**
     * 检查Whisper是否可用
     */
    public function checkWhisperAvailability() {
        $command = 'whisper --version 2>&1';
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    }
    
    /**
     * 获取支持的模型列表
     */
    public function getAvailableModels() {
        $models = ['tiny', 'base', 'small', 'medium', 'large'];
        $availableModels = [];
        
        foreach ($models as $model) {
            $modelPath = $this->modelPath . '/' . $model . '.pt';
            if (file_exists($modelPath)) {
                $availableModels[] = $model;
            }
        }
        
        return $availableModels;
    }
    
    /**
     * 下载Whisper模型
     */
    public function downloadModel($modelName) {
        $command = sprintf(
            'whisper --model %s --download-only',
            $modelName
        );
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception('模型下载失败: ' . implode("\n", $output));
        }
        
        return true;
    }
}
