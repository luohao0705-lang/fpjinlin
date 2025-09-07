<?php
/**
 * 视频分析报告生成器
 * 复盘精灵系统 - 专业报告生成
 */

class VideoReportGenerator {
    private $db;
    private $deepSeekService;
    
    public function __construct() {
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
        
        $this->deepSeekService = new DeepSeekService();
    }
    
    /**
     * 生成视频分析报告
     */
    public function generateReport($orderId) {
        try {
            error_log("开始生成视频分析报告: {$orderId}");
            
            // 获取订单信息
            $order = $this->db->fetchOne(
                "SELECT * FROM video_analysis_orders WHERE id = ?",
                [$orderId]
            );
            
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            // 获取分析数据
            $analysisData = $this->collectAnalysisData($orderId);
            
            // 生成报告内容
            $report = $this->buildReportContent($order, $analysisData);
            
            // 保存报告
            $this->saveReport($orderId, $report);
            
            error_log("视频分析报告生成完成: {$orderId}");
            return $report;
            
        } catch (Exception $e) {
            error_log("视频分析报告生成失败: {$orderId} - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 收集分析数据
     */
    private function collectAnalysisData($orderId) {
        // 获取视频分析结果
        $analysisResults = $this->db->fetchAll(
            "SELECT var.*, vs.segment_index, vs.start_time as segment_start, vs.end_time as segment_end,
                    vf.video_type, vf.video_index
             FROM video_analysis_results var
             LEFT JOIN video_segments vs ON var.segment_id = vs.id
             LEFT JOIN video_files vf ON vs.video_file_id = vf.id
             WHERE vf.order_id = ? AND var.analysis_type = 'comprehensive'
             ORDER BY vf.video_type, vf.video_index, vs.segment_index",
            [$orderId]
        );
        
        // 获取语音识别结果
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
        
        // 获取视频文件信息
        $videoFiles = $this->db->fetchAll(
            "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
            [$orderId]
        );
        
        return [
            'analysis_results' => $analysisResults,
            'transcripts' => $transcripts,
            'video_files' => $videoFiles
        ];
    }
    
    /**
     * 构建报告内容
     */
    private function buildReportContent($order, $analysisData) {
        // 构建分析提示词
        $prompt = $this->buildAnalysisPrompt($order, $analysisData);
        
        // 调用DeepSeek API生成报告
        $response = $this->deepSeekService->callDeepSeekAPI($prompt);
        $result = $this->deepSeekService->parseAnalysisResponse($response);
        
        // 增强报告内容
        $enhancedReport = $this->enhanceReportContent($result, $analysisData);
        
        return $enhancedReport;
    }
    
    /**
     * 构建分析提示词
     */
    private function buildAnalysisPrompt($order, $analysisData) {
        $prompt = "你是一位专业的直播带货分析师，请根据以下视频分析数据生成一份详细的直播复盘分析报告：\n\n";
        
        $prompt .= "## 订单信息\n";
        $prompt .= "- 订单ID: {$order['id']}\n";
        $prompt .= "- 分析标题: {$order['title']}\n";
        $prompt .= "- 创建时间: {$order['created_at']}\n";
        $prompt .= "- 视频数量: " . count($analysisData['video_files']) . "个\n";
        $prompt .= "- 分析切片: " . count($analysisData['analysis_results']) . "个\n\n";
        
        $prompt .= "## 视频分析数据\n";
        foreach ($analysisData['analysis_results'] as $index => $result) {
            $prompt .= "### 切片" . ($index + 1) . " ({$result['video_type']})\n";
            $prompt .= "- 时间范围: " . $this->formatTime($result['segment_start']) . "-" . $this->formatTime($result['segment_end']) . "\n";
            $prompt .= "- 分析结果: " . json_encode($result['result_data'], JSON_UNESCAPED_UNICODE) . "\n\n";
        }
        
        $prompt .= "## 语音识别数据\n";
        $groupedTranscripts = $this->groupTranscriptsByVideo($analysisData['transcripts']);
        foreach ($groupedTranscripts as $videoKey => $transcripts) {
            $prompt .= "### {$videoKey} 字幕\n";
            foreach ($transcripts as $transcript) {
                $prompt .= "- [" . $this->formatTime($transcript['start_time']) . "] " . $transcript['text'] . "\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "## 报告要求\n";
        $prompt .= "请按照以下结构生成专业的分析报告，要求内容详实、分析深入：\n\n";
        
        $prompt .= "### 1. 执行摘要\n";
        $prompt .= "- 分析概述（200字以内）\n";
        $prompt .= "- 关键发现（3-5个要点）\n";
        $prompt .= "- 总体评分（0-100分，详细说明评分依据）\n";
        $prompt .= "- 等级评定（优秀/良好/一般/较差/不合格）\n\n";
        
        $prompt .= "### 2. 时间线分析\n";
        $prompt .= "- 每2-5分钟总结一次关键内容\n";
        $prompt .= "- 话术变化趋势分析\n";
        $prompt .= "- 互动效果时间分布\n";
        $prompt .= "- 销售节奏分析\n\n";
        
        $prompt .= "### 3. 主播表现分析\n";
        $prompt .= "- 情绪曲线分析（开心、紧张、自信、疲惫等）\n";
        $prompt .= "- 语速节奏分析（快、中、慢，停顿频率）\n";
        $prompt .= "- 肢体动作分析（手势、表情、姿态）\n";
        $prompt .= "- 镜头感分析（是否看镜头，与观众互动）\n";
        $prompt .= "- 控场能力评估\n\n";
        
        $prompt .= "### 4. 话术结构分析\n";
        $prompt .= "- 开场话术效果（吸引力、记忆点）\n";
        $prompt .= "- 卖点介绍完整性（产品特点、优势、差异化）\n";
        $prompt .= "- 价格策略分析（定价、优惠、限时策略）\n";
        $prompt .= "- 互动引导效果（提问、点赞、关注引导）\n";
        $prompt .= "- 收尾话术分析（下单引导、感谢表达）\n";
        $prompt .= "- 话术框架完整度评估\n\n";
        
        $prompt .= "### 5. 商品演示分析\n";
        $prompt .= "- 演示方式分析（实物展示、功能演示、对比演示）\n";
        $prompt .= "- 镜头配合效果（特写、全景、多角度）\n";
        $prompt .= "- 证据化程度（数据、对比、证明材料）\n";
        $prompt .= "- 演示路径完整度\n";
        $prompt .= "- 产品展示的视觉冲击力\n\n";
        
        $prompt .= "### 6. 场景氛围分析\n";
        $prompt .= "- 灯光效果分析（明亮度、色温、氛围营造）\n";
        $prompt .= "- 背景布置分析（产品摆放、装饰、品牌元素）\n";
        $prompt .= "- 音效分析（背景音乐、音质、音量控制）\n";
        $prompt .= "- 整体氛围评价（专业、亲民、高端等）\n";
        $prompt .= "- 场景与产品匹配度\n\n";
        
        $prompt .= "### 7. 风险合规检查\n";
        $prompt .= "- 敏感词汇识别（夸大宣传、绝对化用语）\n";
        $prompt .= "- 违规内容检查（虚假宣传、误导消费者）\n";
        $prompt .= "- 合规性评估\n";
        $prompt .= "- 改进建议（如何合规表达）\n\n";
        
        $prompt .= "### 8. 同行对比分析\n";
        $prompt .= "- 节奏对比（快慢节奏、时间分配）\n";
        $prompt .= "- 话术对比（表达方式、卖点突出）\n";
        $prompt .= "- 演示对比（展示方式、视觉效果）\n";
        $prompt .= "- 场景对比（氛围营造、专业度）\n";
        $prompt .= "- 互动对比（观众参与度、反馈效果）\n";
        $prompt .= "- 优劣势分析\n\n";
        
        $prompt .= "### 9. 推荐话术库\n";
        $prompt .= "- 开场话术（5-10条，包含原句和优化版）\n";
        $prompt .= "- 卖点话术（5-10条，突出产品优势）\n";
        $prompt .= "- 异议处理话术（5-10条，应对常见问题）\n";
        $prompt .= "- 福利话术（5-10条，促销和优惠表达）\n";
        $prompt .= "- 收尾话术（5-10条，促成下单）\n\n";
        
        $prompt .= "### 10. 行动清单\n";
        $prompt .= "- 优先级排序（高、中、低）\n";
        $prompt .= "- 具体改进建议（可操作性强）\n";
        $prompt .= "- 实施时间表（短期、中期、长期）\n";
        $prompt .= "- 衡量标准（如何评估改进效果）\n";
        $prompt .= "- 负责人建议（谁负责执行）\n\n";
        
        $prompt .= "### 11. 附录\n";
        $prompt .= "- 关键截图描述\n";
        $prompt .= "- 字幕关键词表\n";
        $prompt .= "- 情绪/语速曲线图\n";
        $prompt .= "- OCR提取文字汇总\n";
        $prompt .= "- 时间轴详细记录\n\n";
        
        $prompt .= "请以JSON格式返回报告，确保结构完整、内容详实、分析深入。";
        
        return $prompt;
    }
    
    /**
     * 增强报告内容
     */
    private function enhanceReportContent($report, $analysisData) {
        // 添加时间戳
        $report['generated_at'] = date('Y-m-d H:i:s');
        $report['report_version'] = '1.0';
        
        // 添加数据统计
        $report['data_summary'] = [
            'total_segments' => count($analysisData['analysis_results']),
            'total_transcripts' => count($analysisData['transcripts']),
            'video_count' => count($analysisData['video_files']),
            'analysis_duration' => $this->calculateTotalDuration($analysisData)
        ];
        
        // 添加图表数据
        $report['charts'] = $this->generateChartData($analysisData);
        
        return $report;
    }
    
    /**
     * 按视频分组字幕
     */
    private function groupTranscriptsByVideo($transcripts) {
        $grouped = [];
        
        foreach ($transcripts as $transcript) {
            $key = $transcript['video_type'] . '_' . $transcript['video_index'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $transcript;
        }
        
        return $grouped;
    }
    
    /**
     * 计算总时长
     */
    private function calculateTotalDuration($analysisData) {
        $totalDuration = 0;
        
        foreach ($analysisData['video_files'] as $videoFile) {
            if ($videoFile['duration']) {
                $totalDuration += $videoFile['duration'];
            }
        }
        
        return $totalDuration;
    }
    
    /**
     * 生成图表数据
     */
    private function generateChartData($analysisData) {
        $charts = [];
        
        // 情绪曲线图
        $charts['emotion_curve'] = $this->generateEmotionCurve($analysisData);
        
        // 语速分析图
        $charts['speech_speed'] = $this->generateSpeechSpeedChart($analysisData);
        
        // 时间线分析图
        $charts['timeline'] = $this->generateTimelineChart($analysisData);
        
        return $charts;
    }
    
    /**
     * 生成情绪曲线
     */
    private function generateEmotionCurve($analysisData) {
        $emotions = [];
        
        foreach ($analysisData['analysis_results'] as $result) {
            $data = json_decode($result['result_data'], true);
            if (isset($data['emotion'])) {
                $emotions[] = [
                    'time' => $result['segment_start'],
                    'emotion' => $data['emotion'],
                    'confidence' => $data['confidence'] ?? 0.8
                ];
            }
        }
        
        return $emotions;
    }
    
    /**
     * 生成语速分析图
     */
    private function generateSpeechSpeedChart($analysisData) {
        $speechData = [];
        
        foreach ($analysisData['transcripts'] as $transcript) {
            $duration = $transcript['end_time'] - $transcript['start_time'];
            $wordCount = mb_strlen($transcript['text']);
            $speed = $duration > 0 ? $wordCount / $duration : 0;
            
            $speechData[] = [
                'time' => $transcript['start_time'],
                'speed' => $speed,
                'word_count' => $wordCount
            ];
        }
        
        return $speechData;
    }
    
    /**
     * 生成时间线分析图
     */
    private function generateTimelineChart($analysisData) {
        $timeline = [];
        
        foreach ($analysisData['analysis_results'] as $result) {
            $timeline[] = [
                'time' => $result['segment_start'],
                'duration' => $result['segment_end'] - $result['segment_start'],
                'video_type' => $result['video_type'],
                'video_index' => $result['video_index'],
                'segment_index' => $result['segment_index']
            ];
        }
        
        return $timeline;
    }
    
    /**
     * 保存报告
     */
    private function saveReport($orderId, $report) {
        $this->db->query(
            "UPDATE video_analysis_orders SET ai_report = ?, report_score = ?, report_level = ?, completed_at = NOW() WHERE id = ?",
            [
                json_encode($report, JSON_UNESCAPED_UNICODE),
                $report['overall_score'] ?? 0,
                $report['level'] ?? 'average',
                $orderId
            ]
        );
    }
    
    /**
     * 格式化时间
     */
    private function formatTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%02d:%02d', $minutes, $seconds);
        }
    }
}
