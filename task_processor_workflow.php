<?php
/**
 * 任务处理器 - 工作流版本
 * 负责处理视频分析工作流的各个阶段
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/VideoAnalysisWorkflow.php';
require_once 'includes/classes/VideoRecorder.php';
require_once 'includes/classes/AIAnalysisService.php';
require_once 'includes/classes/SpeechExtractionService.php';
require_once 'includes/classes/ReportGenerationService.php';

class TaskProcessorWorkflow {
    private $db;
    private $workflow;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->workflow = new VideoAnalysisWorkflow();
    }
    
    /**
     * 处理待处理的任务
     */
    public function processPendingTasks() {
        try {
            $pdo = $this->db->getConnection();
            
            // 获取待处理的任务
            $tasks = $this->getPendingTasks();
            
            foreach ($tasks as $task) {
                $this->processTask($task);
            }
            
            return [
                'success' => true,
                'processed_count' => count($tasks),
                'message' => '任务处理完成'
            ];
            
        } catch (Exception $e) {
            error_log("处理待处理任务失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 处理单个任务
     */
    private function processTask($task) {
        try {
            $taskData = json_decode($task['task_data'], true);
            
            switch ($task['task_type']) {
                case 'record':
                    $this->processRecordingTask($task, $taskData);
                    break;
                    
                case 'transcode':
                    $this->processTranscodingTask($task, $taskData);
                    break;
                    
                case 'ai_analyze':
                    $this->processAIAnalysisTask($task, $taskData);
                    break;
                    
                case 'speech_extract':
                    $this->processSpeechExtractionTask($task, $taskData);
                    break;
                    
                case 'script_analyze':
                    $this->processScriptAnalysisTask($task, $taskData);
                    break;
                    
                case 'generate_report':
                    $this->processReportGenerationTask($task, $taskData);
                    break;
                    
                default:
                    throw new Exception("未知任务类型: {$task['task_type']}");
            }
            
        } catch (Exception $e) {
            error_log("处理任务失败: " . $e->getMessage());
            $this->markTaskAsFailed($task['id'], $e->getMessage());
        }
    }
    
    /**
     * 处理录制任务
     */
    private function processRecordingTask($task, $taskData) {
        $videoFileId = $taskData['video_file_id'];
        $flvUrl = $taskData['flv_url'];
        $duration = $taskData['duration'] ?? 60;
        
        // 标记任务为处理中
        $this->markTaskAsProcessing($task['id']);
        
        // 开始录制
        $recorder = new VideoRecorder();
        $result = $recorder->startRecording($videoFileId, $flvUrl, $duration);
        
        if ($result['success']) {
            $this->markTaskAsCompleted($task['id'], '录制任务已启动');
        } else {
            $this->markTaskAsFailed($task['id'], $result['message']);
        }
    }
    
    /**
     * 处理转码任务
     */
    private function processTranscodingTask($task, $taskData) {
        $videoFileId = $taskData['video_file_id'];
        $inputPath = $taskData['input_path'];
        $outputPath = $taskData['output_path'];
        $resolution = $taskData['resolution'] ?? '720p';
        $bitrate = $taskData['bitrate'] ?? '1500k';
        
        // 标记任务为处理中
        $this->markTaskAsProcessing($task['id']);
        
        // 执行转码
        $transcoder = new VideoTranscoder();
        $result = $transcoder->transcodeVideo($inputPath, $outputPath, $resolution, $bitrate);
        
        if ($result['success']) {
            $this->markTaskAsCompleted($task['id'], '转码任务完成');
        } else {
            $this->markTaskAsFailed($task['id'], $result['message']);
        }
    }
    
    /**
     * 处理AI分析任务
     */
    private function processAIAnalysisTask($task, $taskData) {
        $videoFileId = $taskData['video_file_id'];
        $videoPath = $taskData['video_path'];
        $analysisType = $taskData['analysis_type'];
        
        // 标记任务为处理中
        $this->markTaskAsProcessing($task['id']);
        
        // 执行AI分析
        $aiService = new AIAnalysisService();
        $result = $aiService->analyzeVideoWithQwenOmni($videoPath, $analysisType);
        
        if ($result['success']) {
            // 保存分析结果
            $this->saveVideoAnalysisResult($videoFileId, $result['result']);
            $this->markTaskAsCompleted($task['id'], 'AI分析任务完成');
        } else {
            $this->markTaskAsFailed($task['id'], $result['message']);
        }
    }
    
    /**
     * 处理语音提取任务
     */
    private function processSpeechExtractionTask($task, $taskData) {
        $videoFileId = $taskData['video_file_id'];
        $videoPath = $taskData['video_path'];
        
        // 标记任务为处理中
        $this->markTaskAsProcessing($task['id']);
        
        // 执行语音提取
        $speechService = new SpeechExtractionService();
        $result = $speechService->extractSpeechWithWhisper($videoPath);
        
        if ($result['success']) {
            // 保存转录结果
            $this->saveSpeechTranscript($videoFileId, $result['transcript']);
            $this->markTaskAsCompleted($task['id'], '语音提取任务完成');
        } else {
            $this->markTaskAsFailed($task['id'], $result['message']);
        }
    }
    
    /**
     * 处理话术分析任务
     */
    private function processScriptAnalysisTask($task, $taskData) {
        $orderId = $taskData['order_id'];
        
        // 标记任务为处理中
        $this->markTaskAsProcessing($task['id']);
        
        // 执行话术分析
        $result = $this->workflow->processScriptAnalysis($orderId);
        
        if ($result['success']) {
            $this->markTaskAsCompleted($task['id'], '话术分析任务完成');
        } else {
            $this->markTaskAsFailed($task['id'], $result['message']);
        }
    }
    
    /**
     * 处理报告生成任务
     */
    private function processReportGenerationTask($task, $taskData) {
        $orderId = $taskData['order_id'];
        
        // 标记任务为处理中
        $this->markTaskAsProcessing($task['id']);
        
        // 执行报告生成
        $result = $this->workflow->processReportGeneration($orderId);
        
        if ($result['success']) {
            $this->markTaskAsCompleted($task['id'], '报告生成任务完成');
        } else {
            $this->markTaskAsFailed($task['id'], $result['message']);
        }
    }
    
    /**
     * 获取待处理的任务
     */
    private function getPendingTasks() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM video_processing_queue 
            WHERE status = 'pending' 
            ORDER BY priority DESC, created_at ASC 
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 标记任务为处理中
     */
    private function markTaskAsProcessing($taskId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE video_processing_queue 
            SET status = 'processing', started_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$taskId]);
    }
    
    /**
     * 标记任务为完成
     */
    private function markTaskAsCompleted($taskId, $message = '') {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE video_processing_queue 
            SET status = 'completed', completed_at = NOW(), error_message = ? 
            WHERE id = ?
        ");
        $stmt->execute([$message, $taskId]);
    }
    
    /**
     * 标记任务为失败
     */
    private function markTaskAsFailed($taskId, $errorMessage) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE video_processing_queue 
            SET status = 'failed', error_message = ? 
            WHERE id = ?
        ");
        $stmt->execute([$errorMessage, $taskId]);
    }
    
    /**
     * 保存视频分析结果
     */
    private function saveVideoAnalysisResult($videoFileId, $result) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE video_files 
            SET video_analysis_result = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([json_encode($result), $videoFileId]);
    }
    
    /**
     * 保存语音转录结果
     */
    private function saveSpeechTranscript($videoFileId, $transcript) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE video_files 
            SET speech_transcript = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$transcript, $videoFileId]);
    }
}

// 如果直接运行此脚本
if (php_sapi_name() === 'cli') {
    $processor = new TaskProcessorWorkflow();
    $result = $processor->processPendingTasks();
    
    echo "任务处理结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
}
?>
