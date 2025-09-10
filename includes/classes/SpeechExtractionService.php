<?php
/**
 * 语音提取服务
 * 负责使用Whisper提取视频中的语音内容
 */

require_once 'SystemConfig.php';

class SpeechExtractionService {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = new SystemConfig();
    }
    
    /**
     * 提取视频中的语音
     */
    public function extractSpeechWithWhisper($videoPath) {
        try {
            // 检查视频文件是否存在
            if (!file_exists($videoPath)) {
                throw new Exception("视频文件不存在: {$videoPath}");
            }
            
            // 生成输出文件路径
            $outputPath = $this->generateTranscriptPath($videoPath);
            
            // 构建Whisper命令
            $command = $this->buildWhisperCommand($videoPath, $outputPath);
            
            // 执行Whisper命令
            $result = $this->executeWhisperCommand($command);
            
            if (!$result['success']) {
                throw new Exception("Whisper执行失败: " . $result['error']);
            }
            
            // 读取转录结果
            $transcript = $this->readTranscriptFile($outputPath);
            
            // 处理转录文本
            $processedTranscript = $this->processTranscript($transcript);
            
            return [
                'success' => true,
                'transcript' => $processedTranscript,
                'raw_transcript' => $transcript,
                'output_path' => $outputPath
            ];
            
        } catch (Exception $e) {
            error_log("语音提取失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 处理转录文本
     */
    public function processTranscript($transcript) {
        try {
            // 清理文本
            $cleanedText = $this->cleanTranscriptText($transcript);
            
            // 分段处理
            $segments = $this->segmentTranscript($cleanedText);
            
            // 提取关键信息
            $keyPhrases = $this->extractKeyPhrases($cleanedText);
            
            // 分析语音特征
            $speechAnalysis = $this->analyzeSpeechPatterns($cleanedText);
            
            return [
                'full_text' => $cleanedText,
                'segments' => $segments,
                'key_phrases' => $keyPhrases,
                'speech_analysis' => $speechAnalysis,
                'word_count' => str_word_count($cleanedText),
                'character_count' => mb_strlen($cleanedText)
            ];
            
        } catch (Exception $e) {
            error_log("转录文本处理失败: " . $e->getMessage());
            return [
                'full_text' => $transcript,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 分析语音模式
     */
    public function analyzeSpeechPatterns($transcript) {
        try {
            $analysis = [
                'speaking_speed' => $this->calculateSpeakingSpeed($transcript),
                'repetition_rate' => $this->calculateRepetitionRate($transcript),
                'question_count' => $this->countQuestions($transcript),
                'exclamation_count' => $this->countExclamations($transcript),
                'key_words' => $this->extractKeyWords($transcript),
                'emotional_tone' => $this->analyzeEmotionalTone($transcript)
            ];
            
            return $analysis;
            
        } catch (Exception $e) {
            error_log("语音模式分析失败: " . $e->getMessage());
            return [
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取提取进度
     */
    public function getExtractionProgress($videoFileId) {
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("SELECT * FROM video_files WHERE id = ?");
            $stmt->execute([$videoFileId]);
            $videoFile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$videoFile) {
                return ['completed' => false, 'progress' => 0, 'message' => '视频文件不存在'];
            }
            
            if ($videoFile['status'] === 'speech_extracting') {
                // 检查是否有转录结果
                if ($videoFile['speech_transcript']) {
                    return ['completed' => true, 'progress' => 100, 'message' => '语音提取完成'];
                } else {
                    return ['completed' => false, 'progress' => 50, 'message' => '语音提取中...'];
                }
            } elseif ($videoFile['status'] === 'speech_extraction_completed') {
                return ['completed' => true, 'progress' => 100, 'message' => '语音提取完成'];
            }
            
            return ['completed' => false, 'progress' => 0, 'message' => '未开始提取'];
            
        } catch (Exception $e) {
            error_log("获取语音提取进度失败: " . $e->getMessage());
            return ['completed' => false, 'progress' => 0, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 构建Whisper命令
     */
    private function buildWhisperCommand($videoPath, $outputPath) {
        $whisperPath = $this->config->get('whisper_path', '/usr/local/bin/whisper');
        $modelPath = $this->config->get('whisper_model_path', '/opt/whisper/models');
        $model = $this->config->get('whisper_model', 'base');
        
        // 创建输出目录
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $command = sprintf(
            '%s "%s" --model %s --model_dir "%s" --output_dir "%s" --output_format json --language zh --task transcribe',
            $whisperPath,
            $videoPath,
            $model,
            $modelPath,
            $outputDir
        );
        
        return $command;
    }
    
    /**
     * 执行Whisper命令
     */
    private function executeWhisperCommand($command) {
        $logFile = $this->getLogPath();
        
        // 异步执行命令
        $fullCommand = sprintf(
            'nohup %s > %s 2>&1 & echo $!',
            $command,
            $logFile
        );
        
        $pid = trim(shell_exec($fullCommand));
        
        if (!$pid) {
            return ['success' => false, 'error' => '无法启动Whisper进程'];
        }
        
        // 等待处理完成（最多10分钟）
        $timeout = 600; // 10分钟
        $startTime = time();
        
        while (time() - $startTime < $timeout) {
            if (!$this->isProcessRunning($pid)) {
                // 进程已结束，检查输出文件
                return ['success' => true, 'pid' => $pid];
            }
            sleep(5); // 每5秒检查一次
        }
        
        // 超时，终止进程
        $this->killProcess($pid);
        return ['success' => false, 'error' => '处理超时'];
    }
    
    /**
     * 生成转录文件路径
     */
    private function generateTranscriptPath($videoPath) {
        $baseName = pathinfo($videoPath, PATHINFO_FILENAME);
        $outputDir = $this->config->get('transcript_path', '/storage/transcripts');
        
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        return "{$outputDir}/{$baseName}.json";
    }
    
    /**
     * 读取转录文件
     */
    private function readTranscriptFile($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("转录文件不存在: {$filePath}");
        }
        
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        
        if (!$data) {
            throw new Exception("转录文件格式错误");
        }
        
        // 提取文本内容
        $text = '';
        if (isset($data['segments'])) {
            foreach ($data['segments'] as $segment) {
                $text .= $segment['text'] . ' ';
            }
        } elseif (isset($data['text'])) {
            $text = $data['text'];
        }
        
        return trim($text);
    }
    
    /**
     * 清理转录文本
     */
    private function cleanTranscriptText($text) {
        // 移除多余的空白字符
        $text = preg_replace('/\s+/', ' ', $text);
        
        // 移除特殊字符
        $text = preg_replace('/[^\p{L}\p{N}\p{P}\s]/u', '', $text);
        
        // 标准化标点符号
        $text = str_replace(['，', '。', '？', '！'], [',', '.', '?', '!'], $text);
        
        return trim($text);
    }
    
    /**
     * 分段处理转录文本
     */
    private function segmentTranscript($text) {
        $sentences = preg_split('/[.!?。！？]+/', $text);
        $segments = [];
        
        foreach ($sentences as $index => $sentence) {
            $sentence = trim($sentence);
            if (!empty($sentence)) {
                $segments[] = [
                    'index' => $index + 1,
                    'text' => $sentence,
                    'word_count' => str_word_count($sentence),
                    'character_count' => mb_strlen($sentence)
                ];
            }
        }
        
        return $segments;
    }
    
    /**
     * 提取关键短语
     */
    private function extractKeyPhrases($text) {
        // 简单的关键词提取（可以后续集成更复杂的NLP算法）
        $words = preg_split('/\s+/', $text);
        $wordCount = array_count_values($words);
        
        // 按出现频率排序
        arsort($wordCount);
        
        // 提取前10个高频词
        $keyPhrases = array_slice($wordCount, 0, 10, true);
        
        return $keyPhrases;
    }
    
    /**
     * 计算语速
     */
    private function calculateSpeakingSpeed($text) {
        $wordCount = str_word_count($text);
        $characterCount = mb_strlen($text);
        
        // 假设平均每分钟150字
        $estimatedDuration = $wordCount / 150; // 分钟
        
        return [
            'words_per_minute' => $wordCount / max($estimatedDuration, 1),
            'characters_per_minute' => $characterCount / max($estimatedDuration, 1),
            'estimated_duration' => $estimatedDuration
        ];
    }
    
    /**
     * 计算重复率
     */
    private function calculateRepetitionRate($text) {
        $words = preg_split('/\s+/', $text);
        $uniqueWords = array_unique($words);
        
        $totalWords = count($words);
        $uniqueWordCount = count($uniqueWords);
        
        return [
            'total_words' => $totalWords,
            'unique_words' => $uniqueWordCount,
            'repetition_rate' => ($totalWords - $uniqueWordCount) / max($totalWords, 1) * 100
        ];
    }
    
    /**
     * 统计问句数量
     */
    private function countQuestions($text) {
        return substr_count($text, '?') + substr_count($text, '？');
    }
    
    /**
     * 统计感叹句数量
     */
    private function countExclamations($text) {
        return substr_count($text, '!') + substr_count($text, '！');
    }
    
    /**
     * 提取关键词
     */
    private function extractKeyWords($text) {
        // 简单的关键词提取
        $words = preg_split('/\s+/', $text);
        $wordCount = array_count_values($words);
        
        // 过滤短词和常见词
        $stopWords = ['的', '了', '在', '是', '我', '你', '他', '她', '它', '们', '这', '那', '有', '和', '与', '或', '但', '而', '所以', '因为', '如果', '虽然', '但是'];
        
        $filteredWords = array_filter($wordCount, function($word) use ($stopWords) {
            return mb_strlen($word) > 1 && !in_array($word, $stopWords);
        });
        
        arsort($filteredWords);
        
        return array_slice($filteredWords, 0, 20, true);
    }
    
    /**
     * 分析情感语调
     */
    private function analyzeEmotionalTone($text) {
        $positiveWords = ['好', '棒', '优秀', '完美', '赞', '厉害', '精彩', '太棒了', '非常好', '超级'];
        $negativeWords = ['不好', '差', '糟糕', '失败', '问题', '错误', '困难', '麻烦', '糟糕', '太差了'];
        
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            $positiveCount += substr_count($text, $word);
        }
        
        foreach ($negativeWords as $word) {
            $negativeCount += substr_count($text, $word);
        }
        
        $total = $positiveCount + $negativeCount;
        
        if ($total === 0) {
            return 'neutral';
        }
        
        $positiveRatio = $positiveCount / $total;
        
        if ($positiveRatio > 0.6) {
            return 'positive';
        } elseif ($positiveRatio < 0.4) {
            return 'negative';
        } else {
            return 'neutral';
        }
    }
    
    /**
     * 获取日志文件路径
     */
    private function getLogPath() {
        $logDir = $this->config->get('log_path', '/storage/logs');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        return "{$logDir}/whisper_" . uniqid() . ".log";
    }
    
    /**
     * 检查进程是否在运行
     */
    private function isProcessRunning($pid) {
        if (!$pid) {
            return false;
        }
        
        $result = shell_exec("ps -p {$pid} -o pid= 2>/dev/null");
        return !empty(trim($result));
    }
    
    /**
     * 终止进程
     */
    private function killProcess($pid) {
        if ($pid) {
            shell_exec("kill -9 {$pid} 2>/dev/null");
        }
    }
}
?>
