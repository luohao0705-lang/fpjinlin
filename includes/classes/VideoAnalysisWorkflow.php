<?php
/**
 * 视频分析工作流主控制器
 * 负责管理整个视频分析流程
 */

class VideoAnalysisWorkflow {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = new SystemConfig();
    }
    
    /**
     * 启动视频分析流程
     */
    public function startAnalysis($orderId) {
        try {
            $pdo = $this->db->getConnection();
            
            // 开始事务
            $pdo->beginTransaction();
            
            // 获取订单信息
            $order = $this->getOrder($orderId);
            if (!$order) {
                throw new Exception("订单不存在: {$orderId}");
            }
            
            // 验证订单状态
            if ($order['status'] !== 'pending') {
                throw new Exception("订单状态不正确，当前状态: {$order['status']}");
            }
            
            // 更新订单状态为录制中
            $this->updateOrderStatus($orderId, 'recording', 'recording', 0, '开始录制视频...');
            
            // 创建视频文件记录
            $videoFiles = $this->createVideoFiles($orderId, $order);
            
            // 提交事务
            $pdo->commit();
            
            // 异步启动录制任务
            $this->startRecordingTasks($videoFiles);
            
            return [
                'success' => true,
                'message' => '视频分析已启动',
                'order_id' => $orderId,
                'video_files' => $videoFiles
            ];
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            error_log("启动视频分析失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 处理录制阶段
     */
    public function processRecording($orderId) {
        try {
            $pdo = $this->db->getConnection();
            
            // 获取订单的视频文件
            $videoFiles = $this->getOrderVideoFiles($orderId);
            
            $recordingService = new VideoRecorder();
            $allCompleted = true;
            $completedCount = 0;
            
            foreach ($videoFiles as $videoFile) {
                if ($videoFile['status'] === 'recording') {
                    // 检查录制进度
                    $progress = $recordingService->getRecordingProgress($videoFile['id']);
                    
                    if ($progress['completed']) {
                        // 录制完成
                        $this->updateVideoFileStatus($videoFile['id'], 'recording_completed', 100, '录制完成');
                        $completedCount++;
                    } else {
                        // 更新进度
                        $this->updateVideoFileStatus($videoFile['id'], 'recording', $progress['progress'], $progress['message']);
                        $allCompleted = false;
                    }
                } elseif ($videoFile['status'] === 'recording_completed') {
                    $completedCount++;
                }
            }
            
            // 更新订单进度
            $orderProgress = ($completedCount / count($videoFiles)) * 100;
            $this->updateOrderStatus($orderId, 'recording', 'recording', $orderProgress, "录制进度: {$completedCount}/" . count($videoFiles));
            
            // 如果所有视频录制完成，进入下一阶段
            if ($allCompleted) {
                $this->updateOrderStatus($orderId, 'recording_completed', 'recording_completed', 100, '所有视频录制完成');
                $this->startTranscoding($orderId);
            }
            
            return [
                'success' => true,
                'completed' => $allCompleted,
                'progress' => $orderProgress,
                'completed_count' => $completedCount,
                'total_count' => count($videoFiles)
            ];
            
        } catch (Exception $e) {
            error_log("处理录制阶段失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 开始转码阶段
     */
    public function startTranscoding($orderId) {
        try {
            $this->updateOrderStatus($orderId, 'transcoding', 'transcoding', 0, '开始视频转码...');
            
            $videoFiles = $this->getOrderVideoFiles($orderId);
            $transcodingService = new VideoTranscoder();
            
            foreach ($videoFiles as $videoFile) {
                // 创建转码任务
                $this->createProcessingTask($orderId, 'transcode', [
                    'video_file_id' => $videoFile['id'],
                    'input_path' => $videoFile['file_path'],
                    'output_path' => $this->getTranscodedPath($videoFile['id']),
                    'resolution' => $this->config->get('video_resolution', '720p'),
                    'bitrate' => $this->config->get('video_bitrate', '1500k')
                ]);
            }
            
            return ['success' => true, 'message' => '转码任务已创建'];
            
        } catch (Exception $e) {
            error_log("启动转码失败: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 处理转码阶段
     */
    public function processTranscoding($orderId) {
        try {
            $videoFiles = $this->getOrderVideoFiles($orderId);
            $transcodingService = new VideoTranscoder();
            
            $allCompleted = true;
            $completedCount = 0;
            
            foreach ($videoFiles as $videoFile) {
                if ($videoFile['status'] === 'transcoding') {
                    // 检查转码进度
                    $progress = $transcodingService->getTranscodingProgress($videoFile['id']);
                    
                    if ($progress['completed']) {
                        $this->updateVideoFileStatus($videoFile['id'], 'transcoding_completed', 100, '转码完成');
                        $completedCount++;
                    } else {
                        $this->updateVideoFileStatus($videoFile['id'], 'transcoding', $progress['progress'], $progress['message']);
                        $allCompleted = false;
                    }
                } elseif ($videoFile['status'] === 'transcoding_completed') {
                    $completedCount++;
                }
            }
            
            // 更新订单进度
            $orderProgress = ($completedCount / count($videoFiles)) * 100;
            $this->updateOrderStatus($orderId, 'transcoding', 'transcoding', $orderProgress, "转码进度: {$completedCount}/" . count($videoFiles));
            
            // 如果所有视频转码完成，进入AI分析阶段
            if ($allCompleted) {
                $this->updateOrderStatus($orderId, 'transcoding_completed', 'transcoding_completed', 100, '所有视频转码完成');
                $this->startAIAnalysis($orderId);
            }
            
            return [
                'success' => true,
                'completed' => $allCompleted,
                'progress' => $orderProgress
            ];
            
        } catch (Exception $e) {
            error_log("处理转码阶段失败: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 开始AI分析阶段
     */
    public function startAIAnalysis($orderId) {
        try {
            $this->updateOrderStatus($orderId, 'ai_analyzing', 'ai_analyzing', 0, '开始AI视频分析...');
            
            $videoFiles = $this->getOrderVideoFiles($orderId);
            $aiService = new AIAnalysisService();
            
            foreach ($videoFiles as $videoFile) {
                // 创建AI分析任务
                $this->createProcessingTask($orderId, 'ai_analyze', [
                    'video_file_id' => $videoFile['id'],
                    'video_path' => $this->getTranscodedPath($videoFile['id']),
                    'analysis_type' => $this->getAnalysisType($videoFile['video_type'], $videoFile['video_index'])
                ]);
            }
            
            return ['success' => true, 'message' => 'AI分析任务已创建'];
            
        } catch (Exception $e) {
            error_log("启动AI分析失败: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 处理AI分析阶段
     */
    public function processAIAnalysis($orderId) {
        try {
            $videoFiles = $this->getOrderVideoFiles($orderId);
            $aiService = new AIAnalysisService();
            
            $allCompleted = true;
            $completedCount = 0;
            
            foreach ($videoFiles as $videoFile) {
                if ($videoFile['status'] === 'ai_analyzing') {
                    // 检查AI分析进度
                    $progress = $aiService->getAnalysisProgress($videoFile['id']);
                    
                    if ($progress['completed']) {
                        // 保存分析结果
                        $this->saveVideoAnalysisResult($videoFile['id'], $progress['result']);
                        $this->updateVideoFileStatus($videoFile['id'], 'ai_analysis_completed', 100, 'AI分析完成');
                        $completedCount++;
                    } else {
                        $this->updateVideoFileStatus($videoFile['id'], 'ai_analyzing', $progress['progress'], $progress['message']);
                        $allCompleted = false;
                    }
                } elseif ($videoFile['status'] === 'ai_analysis_completed') {
                    $completedCount++;
                }
            }
            
            // 更新订单进度
            $orderProgress = ($completedCount / count($videoFiles)) * 100;
            $this->updateOrderStatus($orderId, 'ai_analyzing', 'ai_analyzing', $orderProgress, "AI分析进度: {$completedCount}/" . count($videoFiles));
            
            // 如果所有视频AI分析完成，进入语音提取阶段
            if ($allCompleted) {
                $this->updateOrderStatus($orderId, 'ai_analysis_completed', 'ai_analysis_completed', 100, '所有视频AI分析完成');
                $this->startSpeechExtraction($orderId);
            }
            
            return [
                'success' => true,
                'completed' => $allCompleted,
                'progress' => $orderProgress
            ];
            
        } catch (Exception $e) {
            error_log("处理AI分析阶段失败: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 开始语音提取阶段
     */
    public function startSpeechExtraction($orderId) {
        try {
            $this->updateOrderStatus($orderId, 'speech_extracting', 'speech_extracting', 0, '开始语音提取...');
            
            $videoFiles = $this->getOrderVideoFiles($orderId);
            $speechService = new SpeechExtractionService();
            
            foreach ($videoFiles as $videoFile) {
                // 创建语音提取任务
                $this->createProcessingTask($orderId, 'speech_extract', [
                    'video_file_id' => $videoFile['id'],
                    'video_path' => $this->getTranscodedPath($videoFile['id'])
                ]);
            }
            
            return ['success' => true, 'message' => '语音提取任务已创建'];
            
        } catch (Exception $e) {
            error_log("启动语音提取失败: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 处理语音提取阶段
     */
    public function processSpeechExtraction($orderId) {
        try {
            $videoFiles = $this->getOrderVideoFiles($orderId);
            $speechService = new SpeechExtractionService();
            
            $allCompleted = true;
            $completedCount = 0;
            
            foreach ($videoFiles as $videoFile) {
                if ($videoFile['status'] === 'speech_extracting') {
                    // 检查语音提取进度
                    $progress = $speechService->getExtractionProgress($videoFile['id']);
                    
                    if ($progress['completed']) {
                        // 保存转录结果
                        $this->saveSpeechTranscript($videoFile['id'], $progress['transcript']);
                        $this->updateVideoFileStatus($videoFile['id'], 'speech_extraction_completed', 100, '语音提取完成');
                        $completedCount++;
                    } else {
                        $this->updateVideoFileStatus($videoFile['id'], 'speech_extracting', $progress['progress'], $progress['message']);
                        $allCompleted = false;
                    }
                } elseif ($videoFile['status'] === 'speech_extraction_completed') {
                    $completedCount++;
                }
            }
            
            // 更新订单进度
            $orderProgress = ($completedCount / count($videoFiles)) * 100;
            $this->updateOrderStatus($orderId, 'speech_extracting', 'speech_extracting', $orderProgress, "语音提取进度: {$completedCount}/" . count($videoFiles));
            
            // 如果所有语音提取完成，进入话术分析阶段
            if ($allCompleted) {
                $this->updateOrderStatus($orderId, 'speech_extraction_completed', 'speech_extraction_completed', 100, '所有语音提取完成');
                $this->startScriptAnalysis($orderId);
            }
            
            return [
                'success' => true,
                'completed' => $allCompleted,
                'progress' => $orderProgress
            ];
            
        } catch (Exception $e) {
            error_log("处理语音提取阶段失败: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 开始话术分析阶段
     */
    public function startScriptAnalysis($orderId) {
        try {
            $this->updateOrderStatus($orderId, 'script_analyzing', 'script_analyzing', 0, '开始话术分析...');
            
            // 创建话术分析任务
            $this->createProcessingTask($orderId, 'script_analyze', [
                'order_id' => $orderId
            ]);
            
            return ['success' => true, 'message' => '话术分析任务已创建'];
            
        } catch (Exception $e) {
            error_log("启动话术分析失败: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 处理话术分析阶段
     */
    public function processScriptAnalysis($orderId) {
        try {
            $aiService = new AIAnalysisService();
            
            // 获取所有视频的转录文本
            $videoFiles = $this->getOrderVideoFiles($orderId);
            $transcripts = [];
            
            foreach ($videoFiles as $videoFile) {
                $transcript = $this->getSpeechTranscript($videoFile['id']);
                if ($transcript) {
                    $transcripts[] = [
                        'video_file_id' => $videoFile['id'],
                        'video_type' => $videoFile['video_type'],
                        'video_index' => $videoFile['video_index'],
                        'transcript' => $transcript
                    ];
                }
            }
            
            if (empty($transcripts)) {
                throw new Exception("没有找到语音转录文本");
            }
            
            // 分析话术
            $analysisResult = $aiService->analyzeScripts($transcripts);
            
            // 保存分析结果
            $this->saveScriptAnalysisResult($orderId, $analysisResult);
            
            // 更新订单状态
            $this->updateOrderStatus($orderId, 'script_analysis_completed', 'script_analysis_completed', 100, '话术分析完成');
            
            // 开始生成报告
            $this->startReportGeneration($orderId);
            
            return [
                'success' => true,
                'completed' => true,
                'progress' => 100
            ];
            
        } catch (Exception $e) {
            error_log("处理话术分析阶段失败: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 开始报告生成阶段
     */
    public function startReportGeneration($orderId) {
        try {
            $this->updateOrderStatus($orderId, 'report_generating', 'report_generating', 0, '开始生成分析报告...');
            
            // 创建报告生成任务
            $this->createProcessingTask($orderId, 'generate_report', [
                'order_id' => $orderId
            ]);
            
            return ['success' => true, 'message' => '报告生成任务已创建'];
            
        } catch (Exception $e) {
            error_log("启动报告生成失败: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 处理报告生成阶段
     */
    public function processReportGeneration($orderId) {
        try {
            $reportService = new ReportGenerationService();
            
            // 生成最终报告
            $report = $reportService->generateFinalReport($orderId);
            
            // 保存报告
            $this->saveFinalReport($orderId, $report);
            
            // 更新订单状态为完成
            $this->updateOrderStatus($orderId, 'completed', 'completed', 100, '分析报告生成完成');
            
            return [
                'success' => true,
                'completed' => true,
                'report' => $report
            ];
            
        } catch (Exception $e) {
            error_log("处理报告生成阶段失败: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 获取订单信息
     */
    private function getOrder($orderId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM video_analysis_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 创建视频文件记录
     */
    private function createVideoFiles($orderId, $order) {
        $pdo = $this->db->getConnection();
        $videoFiles = [];
        
        // 本方视频
        $videoFiles[] = $this->createVideoFileRecord($orderId, 'self', 0, $order['self_flv_url']);
        
        // 同行视频
        $competitorUrls = json_decode($order['competitor_flv_urls'], true);
        if ($competitorUrls) {
            foreach ($competitorUrls as $index => $url) {
                $videoFiles[] = $this->createVideoFileRecord($orderId, 'competitor', $index + 1, $url);
            }
        }
        
        return $videoFiles;
    }
    
    /**
     * 创建单个视频文件记录
     */
    private function createVideoFileRecord($orderId, $videoType, $videoIndex, $flvUrl) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO video_files 
            (order_id, video_type, video_index, original_url, flv_url, status, processing_stage, stage_progress) 
            VALUES (?, ?, ?, ?, ?, 'pending', 'pending', 0)
        ");
        
        $stmt->execute([$orderId, $videoType, $videoIndex, '', $flvUrl]);
        
        return [
            'id' => $pdo->lastInsertId(),
            'video_type' => $videoType,
            'video_index' => $videoIndex,
            'flv_url' => $flvUrl
        ];
    }
    
    /**
     * 更新订单状态
     */
    private function updateOrderStatus($orderId, $status, $stage, $progress, $message) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            UPDATE video_analysis_orders 
            SET status = ?, current_stage = ?, stage_progress = ?, stage_message = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $stage, $progress, $message, $orderId]);
        
        // 记录进度日志
        $this->logProgress($orderId, $stage, $progress, $message);
    }
    
    /**
     * 更新视频文件状态
     */
    private function updateVideoFileStatus($videoFileId, $status, $progress, $message) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            UPDATE video_files 
            SET status = ?, stage_progress = ?, error_message = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $progress, $message, $videoFileId]);
    }
    
    /**
     * 记录进度日志
     */
    private function logProgress($orderId, $stage, $progress, $message, $videoFileId = null) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO workflow_progress_logs 
            (order_id, stage, progress, message, video_file_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$orderId, $stage, $progress, $message, $videoFileId]);
    }
    
    /**
     * 获取订单的视频文件
     */
    private function getOrderVideoFiles($orderId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 创建处理任务
     */
    private function createProcessingTask($orderId, $taskType, $taskData) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO video_processing_queue 
            (order_id, task_type, task_data, priority, status) 
            VALUES (?, ?, ?, 5, 'pending')
        ");
        
        $stmt->execute([$orderId, $taskType, json_encode($taskData)]);
    }
    
    /**
     * 获取转码后的文件路径
     */
    private function getTranscodedPath($videoFileId) {
        return "/storage/transcoded/video_{$videoFileId}.mp4";
    }
    
    /**
     * 获取分析类型
     */
    private function getAnalysisType($videoType, $videoIndex) {
        if ($videoType === 'self') {
            return 'self';
        } else {
            return 'competitor' . $videoIndex;
        }
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
    
    /**
     * 获取语音转录文本
     */
    private function getSpeechTranscript($videoFileId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT speech_transcript FROM video_files WHERE id = ?");
        $stmt->execute([$videoFileId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['speech_transcript'] : null;
    }
    
    /**
     * 保存话术分析结果
     */
    private function saveScriptAnalysisResult($orderId, $result) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            UPDATE video_analysis_orders 
            SET ai_report = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([json_encode($result), $orderId]);
    }
    
    /**
     * 保存最终报告
     */
    private function saveFinalReport($orderId, $report) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            UPDATE video_analysis_orders 
            SET ai_report = ?, report_score = ?, report_level = ?, completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            json_encode($report),
            $report['summary']['total_score'] ?? 0,
            $report['summary']['level'] ?? 'average',
            $orderId
        ]);
    }
}
?>
