<?php
/**
 * 报告生成服务
 * 负责生成最终的分析报告
 */

class ReportGenerationService {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = new SystemConfig();
    }
    
    /**
     * 生成最终分析报告
     */
    public function generateFinalReport($orderId) {
        try {
            // 获取订单信息
            $order = $this->getOrder($orderId);
            if (!$order) {
                throw new Exception("订单不存在: {$orderId}");
            }
            
            // 获取所有分析结果
            $analysisResults = $this->getAnalysisResults($orderId);
            
            // 生成报告结构
            $report = $this->buildReportStructure($order, $analysisResults);
            
            // 生成详细分析内容
            $report = $this->generateDetailedAnalysis($report, $analysisResults);
            
            // 生成学习建议
            $report = $this->generateLearningSuggestions($report, $analysisResults);
            
            // 计算综合评分
            $report = $this->calculateOverallScore($report, $analysisResults);
            
            // 生成报告摘要
            $report = $this->generateReportSummary($report);
            
            return $report;
            
        } catch (Exception $e) {
            error_log("生成最终报告失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 构建报告结构
     */
    private function buildReportStructure($order, $analysisResults) {
        return [
            'order_info' => [
                'order_id' => $order['id'],
                'order_no' => $order['order_no'],
                'title' => $order['title'],
                'created_at' => $order['created_at'],
                'completed_at' => $order['completed_at']
            ],
            'self_analysis' => null,
            'competitor_analyses' => [],
            'comparison_analysis' => null,
            'learning_suggestions' => [],
            'summary' => [
                'total_score' => 0,
                'level' => 'average',
                'key_insights' => [],
                'action_items' => []
            ]
        ];
    }
    
    /**
     * 生成详细分析内容
     */
    private function generateDetailedAnalysis($report, $analysisResults) {
        foreach ($analysisResults as $result) {
            if ($result['video_type'] === 'self') {
                $report['self_analysis'] = $this->buildSelfAnalysis($result);
            } else {
                $report['competitor_analyses'][] = $this->buildCompetitorAnalysis($result);
            }
        }
        
        // 生成对比分析
        if ($report['self_analysis'] && !empty($report['competitor_analyses'])) {
            $report['comparison_analysis'] = $this->buildComparisonAnalysis(
                $report['self_analysis'], 
                $report['competitor_analyses']
            );
        }
        
        return $report;
    }
    
    /**
     * 构建本方分析
     */
    private function buildSelfAnalysis($result) {
        $videoAnalysis = json_decode($result['video_analysis_result'], true);
        $speechAnalysis = json_decode($result['speech_analysis_result'], true);
        
        return [
            'video_analysis' => $videoAnalysis ?: ['error' => '视频分析数据缺失'],
            'speech_analysis' => $speechAnalysis ?: ['error' => '语音分析数据缺失'],
            'transcript' => $result['speech_transcript'],
            'strengths' => $this->extractStrengths($videoAnalysis, $speechAnalysis),
            'weaknesses' => $this->extractWeaknesses($videoAnalysis, $speechAnalysis),
            'key_insights' => $this->extractKeyInsights($videoAnalysis, $speechAnalysis),
            'improvement_areas' => $this->identifyImprovementAreas($videoAnalysis, $speechAnalysis)
        ];
    }
    
    /**
     * 构建同行分析
     */
    private function buildCompetitorAnalysis($result) {
        $videoAnalysis = json_decode($result['video_analysis_result'], true);
        $speechAnalysis = json_decode($result['speech_analysis_result'], true);
        
        return [
            'competitor_name' => "同行{$result['video_index']}",
            'video_analysis' => $videoAnalysis ?: ['error' => '视频分析数据缺失'],
            'speech_analysis' => $speechAnalysis ?: ['error' => '语音分析数据缺失'],
            'transcript' => $result['speech_transcript'],
            'strengths' => $this->extractStrengths($videoAnalysis, $speechAnalysis),
            'learnable_points' => $this->extractLearnablePoints($videoAnalysis, $speechAnalysis),
            'key_techniques' => $this->extractKeyTechniques($videoAnalysis, $speechAnalysis)
        ];
    }
    
    /**
     * 构建对比分析
     */
    private function buildComparisonAnalysis($selfAnalysis, $competitorAnalyses) {
        $comparison = [
            'overall_comparison' => $this->generateOverallComparison($selfAnalysis, $competitorAnalyses),
            'key_differences' => $this->identifyKeyDifferences($selfAnalysis, $competitorAnalyses),
            'performance_gaps' => $this->calculatePerformanceGaps($selfAnalysis, $competitorAnalyses),
            'competitive_advantages' => $this->identifyCompetitiveAdvantages($selfAnalysis, $competitorAnalyses),
            'areas_for_improvement' => $this->identifyAreasForImprovement($selfAnalysis, $competitorAnalyses)
        ];
        
        return $comparison;
    }
    
    /**
     * 生成学习建议
     */
    private function generateLearningSuggestions($report, $analysisResults) {
        $suggestions = [];
        
        // 基于本方分析生成改进建议
        if ($report['self_analysis']) {
            $suggestions = array_merge($suggestions, $this->generateSelfImprovementSuggestions($report['self_analysis']));
        }
        
        // 基于同行分析生成学习建议
        foreach ($report['competitor_analyses'] as $competitor) {
            $suggestions = array_merge($suggestions, $this->generateCompetitorLearningSuggestions($competitor));
        }
        
        // 基于对比分析生成综合建议
        if ($report['comparison_analysis']) {
            $suggestions = array_merge($suggestions, $this->generateComparisonBasedSuggestions($report['comparison_analysis']));
        }
        
        // 去重并排序
        $suggestions = array_unique($suggestions);
        $report['learning_suggestions'] = array_values($suggestions);
        
        return $report;
    }
    
    /**
     * 计算综合评分
     */
    private function calculateOverallScore($report, $analysisResults) {
        $scores = [];
        
        // 本方评分
        if ($report['self_analysis']) {
            $selfScore = $this->calculateSelfScore($report['self_analysis']);
            $scores[] = $selfScore;
        }
        
        // 同行评分（用于对比）
        foreach ($report['competitor_analyses'] as $competitor) {
            $competitorScore = $this->calculateCompetitorScore($competitor);
            $scores[] = $competitorScore;
        }
        
        // 计算综合评分
        $totalScore = !empty($scores) ? array_sum($scores) / count($scores) : 0;
        $level = $this->determineLevel($totalScore);
        
        $report['summary']['total_score'] = round($totalScore);
        $report['summary']['level'] = $level;
        
        return $report;
    }
    
    /**
     * 生成报告摘要
     */
    private function generateReportSummary($report) {
        $summary = [
            'total_score' => $report['summary']['total_score'],
            'level' => $report['summary']['level'],
            'key_insights' => $this->extractSummaryInsights($report),
            'action_items' => $this->generateActionItems($report),
            'next_steps' => $this->generateNextSteps($report)
        ];
        
        $report['summary'] = $summary;
        
        return $report;
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
     * 获取分析结果
     */
    private function getAnalysisResults($orderId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT vf.*, vad.video_analysis_data, vad.speech_analysis_data, vad.speech_transcript
            FROM video_files vf
            LEFT JOIN video_analysis_details vad ON vf.id = vad.video_file_id
            WHERE vf.order_id = ?
            ORDER BY vf.video_type, vf.video_index
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 提取优势
     */
    private function extractStrengths($videoAnalysis, $speechAnalysis) {
        $strengths = [];
        
        if ($videoAnalysis && isset($videoAnalysis['strengths'])) {
            $strengths = array_merge($strengths, $videoAnalysis['strengths']);
        }
        
        if ($speechAnalysis && isset($speechAnalysis['strengths'])) {
            $strengths = array_merge($strengths, $speechAnalysis['strengths']);
        }
        
        return array_unique($strengths);
    }
    
    /**
     * 提取劣势
     */
    private function extractWeaknesses($videoAnalysis, $speechAnalysis) {
        $weaknesses = [];
        
        if ($videoAnalysis && isset($videoAnalysis['weaknesses'])) {
            $weaknesses = array_merge($weaknesses, $videoAnalysis['weaknesses']);
        }
        
        if ($speechAnalysis && isset($speechAnalysis['weaknesses'])) {
            $weaknesses = array_merge($weaknesses, $speechAnalysis['weaknesses']);
        }
        
        return array_unique($weaknesses);
    }
    
    /**
     * 提取关键洞察
     */
    private function extractKeyInsights($videoAnalysis, $speechAnalysis) {
        $insights = [];
        
        if ($videoAnalysis && isset($videoAnalysis['key_insights'])) {
            $insights[] = $videoAnalysis['key_insights'];
        }
        
        if ($speechAnalysis && isset($speechAnalysis['key_insights'])) {
            $insights[] = $speechAnalysis['key_insights'];
        }
        
        return $insights;
    }
    
    /**
     * 识别改进领域
     */
    private function identifyImprovementAreas($videoAnalysis, $speechAnalysis) {
        $areas = [];
        
        if ($videoAnalysis && isset($videoAnalysis['suggestions'])) {
            $areas = array_merge($areas, $videoAnalysis['suggestions']);
        }
        
        if ($speechAnalysis && isset($speechAnalysis['improvements'])) {
            $areas = array_merge($areas, $speechAnalysis['improvements']);
        }
        
        return array_unique($areas);
    }
    
    /**
     * 提取可学习点
     */
    private function extractLearnablePoints($videoAnalysis, $speechAnalysis) {
        $points = [];
        
        if ($videoAnalysis && isset($videoAnalysis['learnable_points'])) {
            $points = array_merge($points, $videoAnalysis['learnable_points']);
        }
        
        if ($speechAnalysis && isset($speechAnalysis['key_techniques'])) {
            $points = array_merge($points, $speechAnalysis['key_techniques']);
        }
        
        return array_unique($points);
    }
    
    /**
     * 提取关键技术
     */
    private function extractKeyTechniques($videoAnalysis, $speechAnalysis) {
        $techniques = [];
        
        if ($speechAnalysis && isset($speechAnalysis['key_phrases'])) {
            $techniques = array_merge($techniques, array_keys($speechAnalysis['key_phrases']));
        }
        
        return array_unique($techniques);
    }
    
    /**
     * 生成整体对比
     */
    private function generateOverallComparison($selfAnalysis, $competitorAnalyses) {
        $comparison = "整体对比分析：\n\n";
        
        $selfScore = $this->calculateSelfScore($selfAnalysis);
        $competitorScores = array_map([$this, 'calculateCompetitorScore'], $competitorAnalyses);
        $avgCompetitorScore = array_sum($competitorScores) / count($competitorScores);
        
        $comparison .= "本方评分：{$selfScore}/100\n";
        $comparison .= "同行平均评分：" . round($avgCompetitorScore) . "/100\n";
        
        if ($selfScore > $avgCompetitorScore) {
            $comparison .= "优势：本方表现优于同行平均水平\n";
        } else {
            $comparison .= "差距：本方表现低于同行平均水平，需要改进\n";
        }
        
        return $comparison;
    }
    
    /**
     * 识别关键差异
     */
    private function identifyKeyDifferences($selfAnalysis, $competitorAnalyses) {
        $differences = [];
        
        // 比较优势
        $selfStrengths = $selfAnalysis['strengths'] ?? [];
        $competitorStrengths = [];
        foreach ($competitorAnalyses as $competitor) {
            $competitorStrengths = array_merge($competitorStrengths, $competitor['strengths'] ?? []);
        }
        
        $uniqueSelfStrengths = array_diff($selfStrengths, $competitorStrengths);
        $uniqueCompetitorStrengths = array_diff($competitorStrengths, $selfStrengths);
        
        if (!empty($uniqueSelfStrengths)) {
            $differences[] = "本方独有优势：" . implode('、', $uniqueSelfStrengths);
        }
        
        if (!empty($uniqueCompetitorStrengths)) {
            $differences[] = "同行独有优势：" . implode('、', $uniqueCompetitorStrengths);
        }
        
        return $differences;
    }
    
    /**
     * 计算性能差距
     */
    private function calculatePerformanceGaps($selfAnalysis, $competitorAnalyses) {
        $gaps = [];
        
        $selfScore = $this->calculateSelfScore($selfAnalysis);
        foreach ($competitorAnalyses as $index => $competitor) {
            $competitorScore = $this->calculateCompetitorScore($competitor);
            $gap = $selfScore - $competitorScore;
            
            if ($gap > 0) {
                $gaps[] = "相比同行" . ($index + 1) . "：领先{$gap}分";
            } else {
                $gaps[] = "相比同行" . ($index + 1) . "：落后" . abs($gap) . "分";
            }
        }
        
        return $gaps;
    }
    
    /**
     * 识别竞争优势
     */
    private function identifyCompetitiveAdvantages($selfAnalysis, $competitorAnalyses) {
        return $selfAnalysis['strengths'] ?? [];
    }
    
    /**
     * 识别改进领域
     */
    private function identifyAreasForImprovement($selfAnalysis, $competitorAnalyses) {
        $improvements = $selfAnalysis['weaknesses'] ?? [];
        
        // 添加同行优势作为学习目标
        foreach ($competitorAnalyses as $competitor) {
            $learnablePoints = $competitor['learnable_points'] ?? [];
            $improvements = array_merge($improvements, $learnablePoints);
        }
        
        return array_unique($improvements);
    }
    
    /**
     * 生成本方改进建议
     */
    private function generateSelfImprovementSuggestions($selfAnalysis) {
        $suggestions = [];
        
        $weaknesses = $selfAnalysis['weaknesses'] ?? [];
        foreach ($weaknesses as $weakness) {
            $suggestions[] = "改进建议：{$weakness}";
        }
        
        $improvementAreas = $selfAnalysis['improvement_areas'] ?? [];
        foreach ($improvementAreas as $area) {
            $suggestions[] = "重点改进：{$area}";
        }
        
        return $suggestions;
    }
    
    /**
     * 生成同行学习建议
     */
    private function generateCompetitorLearningSuggestions($competitor) {
        $suggestions = [];
        
        $learnablePoints = $competitor['learnable_points'] ?? [];
        foreach ($learnablePoints as $point) {
            $suggestions[] = "学习同行优势：{$point}";
        }
        
        $keyTechniques = $competitor['key_techniques'] ?? [];
        foreach ($keyTechniques as $technique) {
            $suggestions[] = "学习话术技巧：{$technique}";
        }
        
        return $suggestions;
    }
    
    /**
     * 生成基于对比的建议
     */
    private function generateComparisonBasedSuggestions($comparisonAnalysis) {
        $suggestions = [];
        
        $areasForImprovement = $comparisonAnalysis['areas_for_improvement'] ?? [];
        foreach ($areasForImprovement as $area) {
            $suggestions[] = "对比分析建议：{$area}";
        }
        
        return $suggestions;
    }
    
    /**
     * 计算本方评分
     */
    private function calculateSelfScore($selfAnalysis) {
        $videoScore = $selfAnalysis['video_analysis']['overall_score'] ?? 0;
        $speechScore = $selfAnalysis['speech_analysis']['script_score'] ?? 0;
        
        return ($videoScore + $speechScore) / 2;
    }
    
    /**
     * 计算同行评分
     */
    private function calculateCompetitorScore($competitor) {
        $videoScore = $competitor['video_analysis']['overall_score'] ?? 0;
        $speechScore = $competitor['speech_analysis']['script_score'] ?? 0;
        
        return ($videoScore + $speechScore) / 2;
    }
    
    /**
     * 确定等级
     */
    private function determineLevel($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 80) return 'good';
        if ($score >= 70) return 'average';
        if ($score >= 60) return 'poor';
        return 'unqualified';
    }
    
    /**
     * 提取摘要洞察
     */
    private function extractSummaryInsights($report) {
        $insights = [];
        
        if ($report['self_analysis']) {
            $insights = array_merge($insights, $report['self_analysis']['key_insights'] ?? []);
        }
        
        if ($report['comparison_analysis']) {
            $insights[] = "对比分析显示存在明显差异";
        }
        
        return array_unique($insights);
    }
    
    /**
     * 生成行动项目
     */
    private function generateActionItems($report) {
        $actionItems = [];
        
        // 基于学习建议生成行动项目
        foreach ($report['learning_suggestions'] as $suggestion) {
            $actionItems[] = "立即行动：{$suggestion}";
        }
        
        // 基于改进领域生成行动项目
        if ($report['self_analysis']) {
            $improvementAreas = $report['self_analysis']['improvement_areas'] ?? [];
            foreach ($improvementAreas as $area) {
                $actionItems[] = "制定计划：改进{$area}";
            }
        }
        
        return array_unique($actionItems);
    }
    
    /**
     * 生成下一步
     */
    private function generateNextSteps($report) {
        $nextSteps = [
            "定期进行直播复盘分析",
            "持续学习同行优秀经验",
            "根据分析结果调整直播策略",
            "建立个人话术库和技巧库"
        ];
        
        return $nextSteps;
    }
}
?>
