<?php
/**
 * 视频分析引擎
 * 复盘精灵系统 - 综合视频分析
 */

class VideoAnalysisEngine {
    private $db;
    private $whisperService;
    private $qwenOmniService;
    private $deepSeekService;
    
    public function __construct() {
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
        
        $this->whisperService = new WhisperService();
        $this->qwenOmniService = new QwenOmniService();
        $this->deepSeekService = new DeepSeekService();
    }
    
    /**
     * 处理视频分析订单
     */
    public function processVideoAnalysis($orderId) {
        try {
            error_log("开始处理视频分析订单: {$orderId}");
            
            // 更新订单状态为处理中
            $videoOrder = new VideoAnalysisOrder();
            $videoOrder->updateOrderStatus($orderId, 'processing');
            
            // 获取订单信息
            $order = $videoOrder->getOrderById($orderId);
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            // 获取视频文件列表
            $videoFiles = $this->db->fetchAll(
                "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
                [$orderId]
            );
            
            // 处理每个视频文件
            foreach ($videoFiles as $videoFile) {
                $this->processVideoFile($videoFile);
            }
            
            // 生成综合分析报告
            $report = $this->generateComprehensiveReport($orderId);
            
            // 更新订单状态为完成
            $videoOrder->updateOrderStatus($orderId, 'completed', json_encode($report, JSON_UNESCAPED_UNICODE));
            
            error_log("视频分析订单处理完成: {$orderId}");
            return $report;
            
        } catch (Exception $e) {
            error_log("视频分析订单处理失败: {$orderId} - " . $e->getMessage());
            
            // 更新订单状态为失败
            $videoOrder = new VideoAnalysisOrder();
            $videoOrder->updateOrderStatus($orderId, 'failed', null, null, null, $e->getMessage());
            
            throw $e;
        }
    }
    
    /**
     * 处理单个视频文件
     */
    private function processVideoFile($videoFile) {
        try {
            error_log("开始处理视频文件: {$videoFile['id']}");
            
            // 下载视频
            $videoProcessor = new VideoProcessor();
            $videoProcessor->downloadVideo($videoFile['id'], $videoFile['flv_url']);
            
            // 转码视频
            $videoProcessor->transcodeVideo($videoFile['id']);
            
            // 视频切片
            $videoProcessor->segmentVideo($videoFile['id']);
            
            // 获取切片列表
            $segments = $this->db->fetchAll(
                "SELECT * FROM video_segments WHERE video_file_id = ? ORDER BY segment_index",
                [$videoFile['id']]
            );
            
            // 处理每个切片
            foreach ($segments as $segment) {
                $this->processSegment($segment);
            }
            
            error_log("视频文件处理完成: {$videoFile['id']}");
            
        } catch (Exception $e) {
            error_log("视频文件处理失败: {$videoFile['id']} - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 处理单个视频切片
     */
    private function processSegment($segment) {
        try {
            error_log("开始处理视频切片: {$segment['id']}");
            
            // 语音识别
            $this->whisperService->processSegment($segment['id']);
            
            // 视频理解分析
            $this->qwenOmniService->analyzeSegment($segment['id']);
            
            error_log("视频切片处理完成: {$segment['id']}");
            
        } catch (Exception $e) {
            error_log("视频切片处理失败: {$segment['id']} - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 生成综合分析报告
     */
    private function generateComprehensiveReport($orderId) {
        try {
            error_log("开始生成综合分析报告: {$orderId}");
            
            // 获取订单信息
            $order = $this->db->fetchOne(
                "SELECT * FROM video_analysis_orders WHERE id = ?",
                [$orderId]
            );
            
            // 获取所有分析结果
            $analysisResults = $this->qwenOmniService->getOrderAnalysisResults($orderId);
            $transcripts = $this->whisperService->getOrderTranscripts($orderId);
            
            // 构建报告数据
            $reportData = [
                'order_info' => $order,
                'analysis_results' => $analysisResults,
                'transcripts' => $transcripts,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            // 使用DeepSeek生成最终报告
            $finalReport = $this->generateFinalReport($reportData);
            
            error_log("综合分析报告生成完成: {$orderId}");
            return $finalReport;
            
        } catch (Exception $e) {
            error_log("综合分析报告生成失败: {$orderId} - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 使用DeepSeek生成最终报告
     */
    private function generateFinalReport($reportData) {
        try {
            // 构建分析提示词
            $prompt = $this->buildFinalReportPrompt($reportData);
            
            // 调用DeepSeek API
            $response = $this->deepSeekService->callDeepSeekAPI($prompt);
            
            // 解析响应
            $result = $this->deepSeekService->parseAnalysisResponse($response);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("DeepSeek报告生成失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 构建最终报告提示词
     */
    private function buildFinalReportPrompt($reportData) {
        // 获取订单信息
        $orderId = $reportData['order_info']['id'];
        
        // 获取所有视频文件的转录文本
        $transcripts = $this->db->fetchAll(
            "SELECT vt.transcript_text FROM video_transcripts vt JOIN video_files vf ON vt.video_file_id = vf.id WHERE vf.order_id = ? ORDER BY vf.video_index, vt.segment_index",
            [$orderId]
        );

        $fullTranscript = "";
        if (!empty($transcripts)) {
            foreach ($transcripts as $t) {
                $fullTranscript .= $t['transcript_text'] . "\n";
            }
        } else {
            $fullTranscript = "No transcripts available for this order.";
        }

        // 获取所有视频分析结果
        $analysisResults = $this->db->fetchAll(
            "SELECT var.analysis_result FROM video_analysis_results var JOIN video_segments vs ON var.video_segment_id = vs.id JOIN video_files vf ON vs.video_file_id = vf.id WHERE vf.order_id = ? ORDER BY vf.video_index, vs.segment_index",
            [$orderId]
        );

        $combinedAnalysis = "";
        if (!empty($analysisResults)) {
            foreach ($analysisResults as $ar) {
                $combinedAnalysis .= $ar['analysis_result'] . "\n";
            }
        } else {
            $combinedAnalysis = "No segment analysis results available for this order.";
        }

        // 构建最终的报告提示
        $prompt = "请根据以下直播视频的转录文本和分段分析结果，生成一份全面的直播复盘报告。报告应包含以下几个部分：\n\n";
        $prompt .= "1. **直播概览**：简要总结直播内容和主题。\n";
        $prompt .= "2. **关键亮点**：提取直播中最精彩、最吸引人的瞬间或内容。\n";
        $prompt .= "3. **用户互动分析**：根据转录文本中提及的互动内容，分析用户参与度。\n";
        $prompt .= "4. **内容优化建议**：基于分析结果，提出未来直播内容或形式的改进建议。\n";
        $prompt .= "5. **潜在风险提示**：指出直播中可能存在的敏感词、不当言论或潜在风险点。\n\n";
        $prompt .= "--- 原始数据 ---\n\n";
        $prompt .= "### 直播转录文本:\n" . $fullTranscript . "\n\n";
        $prompt .= "### 分段分析结果:\n" . $combinedAnalysis . "\n\n";
        $prompt .= "请确保报告内容客观、准确，并具有可操作性。";

        return $prompt;
        $prompt .= "- 改进建议\n\n";
        
        $prompt .= "### 8. 同行对比分析\n";
        $prompt .= "- 节奏对比\n";
        $prompt .= "- 话术对比\n";
        $prompt .= "- 演示对比\n";
        $prompt .= "- 场景对比\n";
        $prompt .= "- 互动对比\n\n";
        
        $prompt .= "### 9. 推荐话术库\n";
        $prompt .= "- 开场话术(5-10条)\n";
        $prompt .= "- 卖点话术(5-10条)\n";
        $prompt .= "- 异议处理话术(5-10条)\n";
        $prompt .= "- 福利话术(5-10条)\n";
        $prompt .= "- 收尾话术(5-10条)\n\n";
        
        $prompt .= "### 10. 行动清单\n";
        $prompt .= "- 优先级排序\n";
        $prompt .= "- 具体改进建议\n";
        $prompt .= "- 实施时间表\n";
        $prompt .= "- 衡量标准\n\n";
        
        $prompt .= "请以JSON格式返回报告，包含所有模块的详细内容。";
        
        return $prompt;
    }
    
    /**
     * 获取处理进度
     */
    public function getProcessingProgress($orderId) {
        try {
            // 获取订单状态
            $order = $this->db->fetchOne(
                "SELECT status, created_at, processing_started_at, completed_at FROM video_analysis_orders WHERE id = ?",
                [$orderId]
            );
            
            if (!$order) {
                return ['status' => 'not_found'];
            }
            
            $progress = [
                'status' => $order['status'],
                'created_at' => $order['created_at'],
                'processing_started_at' => $order['processing_started_at'],
                'completed_at' => $order['completed_at']
            ];
            
            // 如果正在处理，计算详细进度
            if ($order['status'] === 'processing') {
                $progress['details'] = $this->calculateDetailedProgress($orderId);
            }
            
            return $progress;
            
        } catch (Exception $e) {
            error_log("获取处理进度失败: {$orderId} - " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 计算详细进度
     */
    private function calculateDetailedProgress($orderId) {
        // 获取视频文件处理状态
        $videoFiles = $this->db->fetchAll(
            "SELECT status FROM video_files WHERE order_id = ?",
            [$orderId]
        );
        
        $totalFiles = count($videoFiles);
        $completedFiles = 0;
        $failedFiles = 0;
        
        foreach ($videoFiles as $file) {
            if ($file['status'] === 'completed') {
                $completedFiles++;
            } elseif ($file['status'] === 'failed') {
                $failedFiles++;
            }
        }
        
        // 获取切片处理状态
        $segments = $this->db->fetchAll(
            "SELECT status FROM video_segments vs 
             LEFT JOIN video_files vf ON vs.video_file_id = vf.id 
             WHERE vf.order_id = ?",
            [$orderId]
        );
        
        $totalSegments = count($segments);
        $completedSegments = 0;
        
        foreach ($segments as $segment) {
            if ($segment['status'] === 'completed') {
                $completedSegments++;
            }
        }
        
        return [
            'video_files' => [
                'total' => $totalFiles,
                'completed' => $completedFiles,
                'failed' => $failedFiles,
                'progress' => $totalFiles > 0 ? round($completedFiles / $totalFiles * 100, 2) : 0
            ],
            'segments' => [
                'total' => $totalSegments,
                'completed' => $completedSegments,
                'progress' => $totalSegments > 0 ? round($completedSegments / $totalSegments * 100, 2) : 0
            ]
        ];
    }
}
