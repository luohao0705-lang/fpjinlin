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
        $prompt = "你是一位专业的直播带货分析师，请根据以下视频分析数据生成一份详细的直播复盘分析报告：\n\n";
        
        $prompt .= "## 分析数据概览\n";
        $prompt .= "- 订单ID: {$reportData['order_info']['id']}\n";
        $prompt .= "- 分析标题: {$reportData['order_info']['title']}\n";
        $prompt .= "- 视频数量: " . count($reportData['analysis_results']) . "个切片\n";
        $prompt .= "- 生成时间: {$reportData['generated_at']}\n\n";
        
        $prompt .= "## 分析结果数据\n";
        foreach ($reportData['analysis_results'] as $index => $result) {
            $prompt .= "### 切片" . ($index + 1) . "\n";
            $prompt .= "- 时间范围: {$result['segment_start']}-{$result['segment_end']}\n";
            $prompt .= "- 视频类型: {$result['video_type']}\n";
            $prompt .= "- 分析结果: " . json_encode($result['result_data'], JSON_UNESCAPED_UNICODE) . "\n\n";
        }
        
        $prompt .= "## 报告要求\n";
        $prompt .= "请按照以下结构生成专业的分析报告：\n\n";
        
        $prompt .= "### 1. 执行摘要\n";
        $prompt .= "- 分析概述\n";
        $prompt .= "- 关键发现\n";
        $prompt .= "- 总体评分(0-100分)\n\n";
        
        $prompt .= "### 2. 时间线分析\n";
        $prompt .= "- 每2-5分钟总结一次\n";
        $prompt .= "- 话术变化趋势\n";
        $prompt .= "- 互动效果分析\n\n";
        
        $prompt .= "### 3. 主播表现分析\n";
        $prompt .= "- 情绪曲线分析\n";
        $prompt .= "- 语速节奏分析\n";
        $prompt .= "- 肢体动作分析\n";
        $prompt .= "- 镜头感分析\n\n";
        
        $prompt .= "### 4. 话术结构分析\n";
        $prompt .= "- 开场话术效果\n";
        $prompt .= "- 卖点介绍完整性\n";
        $prompt .= "- 价格策略分析\n";
        $prompt .= "- 互动引导效果\n";
        $prompt .= "- 收尾话术分析\n\n";
        
        $prompt .= "### 5. 商品演示分析\n";
        $prompt .= "- 演示方式分析\n";
        $prompt .= "- 镜头配合效果\n";
        $prompt .= "- 证据化程度\n";
        $prompt .= "- 演示完整性\n\n";
        
        $prompt .= "### 6. 场景氛围分析\n";
        $prompt .= "- 灯光效果分析\n";
        $prompt .= "- 背景布置分析\n";
        $prompt .= "- 音效分析\n";
        $prompt .= "- 整体氛围评价\n\n";
        
        $prompt .= "### 7. 风险合规检查\n";
        $prompt .= "- 敏感词汇识别\n";
        $prompt .= "- 违规内容检查\n";
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
