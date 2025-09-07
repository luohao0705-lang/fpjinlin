<?php
/**
 * AI分析器类
 * 复盘精灵系统 - DeepSeek API集成
 */

require_once __DIR__ . '/../config/config.php';

class AIAnalyzer {
    private $db;
    private $apiKey;
    private $apiUrl;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->apiKey = getSystemConfig('deepseek_api_key');
        $this->apiUrl = getSystemConfig('deepseek_api_url', DEEPSEEK_API_URL);
    }
    
    /**
     * 执行完整的AI分析流程
     */
    public function analyzeOrder($orderId) {
        try {
            // 获取订单详情
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            // 更新状态为处理中
            $this->updateOrderStatus($orderId, 'processing');
            
            // 构建分析提示词
            $prompt = $this->buildAnalysisPrompt($order);
            
            // 调用DeepSeek API进行分析
            $analysisResult = $this->callDeepSeekAPI($prompt);
            
            // 处理和格式化分析结果
            $formattedResult = $this->formatAnalysisResult($analysisResult);
            
            // 保存分析结果
            $this->saveAnalysisResult($orderId, $formattedResult);
            
            // 更新订单状态为完成
            $this->updateOrderStatus($orderId, 'completed');
            
            // 发送完成通知
            $this->sendCompletionNotification($order['user_id'], $order['title']);
            
            return $formattedResult;
            
        } catch (Exception $e) {
            // 分析失败，更新状态并记录错误
            $this->updateOrderStatus($orderId, 'failed');
            $this->logAnalysisError($orderId, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取订单详情及相关数据
     */
    private function getOrderDetails($orderId) {
        // 获取订单基础信息
        $order = $this->db->fetchOne(
            "SELECT ao.*, u.phone, u.nickname 
             FROM analysis_orders ao 
             LEFT JOIN users u ON ao.user_id = u.id 
             WHERE ao.id = ?",
            [$orderId]
        );
        
        if (!$order) {
            return null;
        }
        
        // 获取订单截图
        $screenshots = $this->db->fetchAll(
            "SELECT * FROM order_screenshots WHERE order_id = ? ORDER BY image_type",
            [$orderId]
        );
        
        $order['screenshots'] = $screenshots;
        
        return $order;
    }
    
    /**
     * 构建AI分析提示词
     */
    private function buildAnalysisPrompt($order) {
        $prompt = "你是一位专业的直播带货分析专家，请对以下直播复盘数据进行深度分析。\n\n";
        
        // 基础信息
        $prompt .= "## 分析任务\n";
        $prompt .= "**分析标题**: " . $order['title'] . "\n";
        $prompt .= "**分析时间**: " . date('Y年m月d日') . "\n\n";
        
        // 本方话术
        $prompt .= "## 本方直播话术\n";
        $prompt .= "```\n" . $order['own_script'] . "\n```\n\n";
        
        // 同行话术（如果有）
        $competitorScripts = [];
        if (!empty($order['competitor1_script'])) {
            $competitorScripts[] = $order['competitor1_script'];
        }
        if (!empty($order['competitor2_script'])) {
            $competitorScripts[] = $order['competitor2_script'];
        }
        if (!empty($order['competitor3_script'])) {
            $competitorScripts[] = $order['competitor3_script'];
        }
        
        if (!empty($competitorScripts)) {
            $prompt .= "## 同行话术参考\n";
            foreach ($competitorScripts as $index => $script) {
                $prompt .= "### 同行" . ($index + 1) . "\n";
                $prompt .= "```\n" . $script . "\n```\n\n";
            }
        }
        
        // 数据截图信息
        if (!empty($order['screenshots'])) {
            $prompt .= "## 直播数据截图\n";
            $prompt .= "本次分析包含 " . count($order['screenshots']) . " 张直播数据截图，包含观看人数、互动数据、转化数据等信息。\n\n";
        }
        
        // 分析要求
        $prompt .= "## 分析要求\n";
        $prompt .= "请基于以上信息进行专业的直播复盘分析，要求：\n\n";
        
        $prompt .= "### 1. 综合评分与等级\n";
        $prompt .= "- 给出0-100分的综合评分\n";
        $prompt .= "- 等级划分：优秀(90-100分)、良好(80-89分)、一般(70-79分)、较差(60-69分)、不合格(0-59分)\n\n";
        
        $prompt .= "### 2. 本方话术深度分析\n";
        $prompt .= "- 话术结构分析（开场、产品介绍、互动引导、促单转化、收尾等）\n";
        $prompt .= "- 语言表达技巧（情感调动、紧迫感营造、信任建立等）\n";
        $prompt .= "- 优势亮点识别\n";
        $prompt .= "- 不足和改进空间\n\n";
        
        if (!empty($competitorScripts)) {
            $prompt .= "### 3. 同行对比分析\n";
            $prompt .= "- 话术差异对比\n";
            $prompt .= "- 优劣势分析\n";
            $prompt .= "- 可学习的亮点\n";
            $prompt .= "- 差异化竞争建议\n\n";
        }
        
        $prompt .= "### 4. 具体改进方案\n";
        $prompt .= "- 话术优化建议（具体的修改建议）\n";
        $prompt .= "- 互动技巧提升\n";
        $prompt .= "- 转化率提升策略\n";
        $prompt .= "- 用户体验优化\n\n";
        
        $prompt .= "### 5. 数据洞察（如有截图数据）\n";
        $prompt .= "- 关键数据指标分析\n";
        $prompt .= "- 数据趋势解读\n";
        $prompt .= "- 数据驱动的优化建议\n\n";
        
        $prompt .= "## 输出格式要求\n";
        $prompt .= "请以JSON格式返回分析结果，包含以下字段：\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= '  "score": 85,  // 综合评分(0-100)' . "\n";
        $prompt .= '  "level": "良好",  // 等级评定' . "\n";
        $prompt .= '  "summary": "整体表现总结...",  // 简要总结' . "\n";
        $prompt .= '  "strengths": ["优势1", "优势2", "优势3"],  // 主要优势' . "\n";
        $prompt .= '  "weaknesses": ["不足1", "不足2", "不足3"],  // 主要不足' . "\n";
        $prompt .= '  "detailed_analysis": "详细的话术分析内容...",  // 详细分析' . "\n";
        $prompt .= '  "competitor_comparison": "同行对比分析内容...",  // 对比分析' . "\n";
        $prompt .= '  "optimization_suggestions": "具体的优化建议...",  // 改进建议' . "\n";
        $prompt .= '  "key_improvements": ["改进点1", "改进点2", "改进点3"],  // 关键改进点' . "\n";
        $prompt .= '  "data_insights": "数据洞察内容..."  // 数据分析' . "\n";
        $prompt .= "}\n";
        $prompt .= "```\n\n";
        
        $prompt .= "请确保分析内容专业、详细、实用，能够为主播提供真正有价值的改进指导。";
        
        return $prompt;
    }
    
    /**
     * 调用DeepSeek API
     */
    private function callDeepSeekAPI($prompt) {
        if (empty($this->apiKey)) {
            throw new Exception('DeepSeek API密钥未配置');
        }
        
        $requestData = [
            'model' => 'deepseek-chat',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是一位专业的直播带货分析师，具有丰富的电商直播经验和数据分析能力。请提供专业、详细、实用的分析报告。'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 4000,
            'stream' => false
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'User-Agent: FPJinLin/1.0'
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("API调用网络错误: {$curlError}");
        }
        
        if ($httpCode !== 200) {
            $errorInfo = json_decode($response, true);
            $errorMessage = $errorInfo['error']['message'] ?? "HTTP错误码: {$httpCode}";
            throw new Exception("API调用失败: {$errorMessage}");
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new Exception("API响应格式错误: 无法解析JSON");
        }
        
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("API响应格式错误: 缺少分析内容");
        }
        
        return $result['choices'][0]['message']['content'];
    }
    
    /**
     * 格式化分析结果
     */
    private function formatAnalysisResult($apiResponse) {
        // 尝试解析JSON格式的响应
        $jsonStart = strpos($apiResponse, '{');
        $jsonEnd = strrpos($apiResponse, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonContent = substr($apiResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
            $parsed = json_decode($jsonContent, true);
            
            if ($parsed && json_last_error() === JSON_ERROR_NONE) {
                // 验证必要字段
                $result = [
                    'score' => $parsed['score'] ?? 75,
                    'level' => $parsed['level'] ?? $this->getScoreLevel($parsed['score'] ?? 75),
                    'summary' => $parsed['summary'] ?? '',
                    'strengths' => $parsed['strengths'] ?? [],
                    'weaknesses' => $parsed['weaknesses'] ?? [],
                    'detailed_analysis' => $parsed['detailed_analysis'] ?? '',
                    'competitor_comparison' => $parsed['competitor_comparison'] ?? '',
                    'optimization_suggestions' => $parsed['optimization_suggestions'] ?? '',
                    'key_improvements' => $parsed['key_improvements'] ?? [],
                    'data_insights' => $parsed['data_insights'] ?? '',
                    'raw_response' => $apiResponse,
                    'analysis_time' => date('Y-m-d H:i:s'),
                    'api_version' => 'deepseek-chat'
                ];
                
                return $result;
            }
        }
        
        // 如果不是JSON格式，尝试解析文本内容
        return $this->parseTextResponse($apiResponse);
    }
    
    /**
     * 解析文本格式的响应
     */
    private function parseTextResponse($response) {
        // 基础格式化
        $result = [
            'score' => $this->extractScore($response),
            'level' => '',
            'summary' => $this->extractSection($response, '总结', '概述', '整体'),
            'strengths' => $this->extractList($response, '优势', '亮点', '优点'),
            'weaknesses' => $this->extractList($response, '不足', '缺点', '问题'),
            'detailed_analysis' => $this->extractSection($response, '详细分析', '深度分析', '话术分析'),
            'competitor_comparison' => $this->extractSection($response, '对比分析', '同行对比', '竞争分析'),
            'optimization_suggestions' => $this->extractSection($response, '优化建议', '改进建议', '提升建议'),
            'key_improvements' => $this->extractList($response, '改进点', '关键改进', '重点改进'),
            'data_insights' => $this->extractSection($response, '数据洞察', '数据分析', '数据解读'),
            'raw_response' => $response,
            'analysis_time' => date('Y-m-d H:i:s'),
            'api_version' => 'deepseek-chat'
        ];
        
        $result['level'] = $this->getScoreLevel($result['score']);
        
        return $result;
    }
    
    /**
     * 提取评分
     */
    private function extractScore($text) {
        // 查找评分模式
        if (preg_match('/(?:评分|得分|分数)[:：]?\s*(\d+)(?:分|\/100)?/u', $text, $matches)) {
            return intval($matches[1]);
        }
        
        if (preg_match('/(\d+)(?:分|\/100)/u', $text, $matches)) {
            return intval($matches[1]);
        }
        
        // 默认评分
        return 75;
    }
    
    /**
     * 提取文本段落
     */
    private function extractSection($text, ...$keywords) {
        foreach ($keywords as $keyword) {
            $pattern = '/(?:' . preg_quote($keyword, '/') . ')[:：]?\s*(.*?)(?=\n\n|\n#|$)/us';
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return '';
    }
    
    /**
     * 提取列表项
     */
    private function extractList($text, ...$keywords) {
        $items = [];
        
        foreach ($keywords as $keyword) {
            $pattern = '/(?:' . preg_quote($keyword, '/') . ')[:：]?\s*(.*?)(?=\n\n|\n#|$)/us';
            if (preg_match($pattern, $text, $matches)) {
                $content = trim($matches[1]);
                
                // 提取列表项
                if (preg_match_all('/(?:[-•*]\s*|^\d+[.、]\s*)(.*?)$/um', $content, $listMatches)) {
                    $items = array_merge($items, array_filter($listMatches[1]));
                } else {
                    // 如果不是列表格式，按句号分割
                    $sentences = array_filter(explode('。', $content));
                    $items = array_merge($items, array_map('trim', $sentences));
                }
                
                break;
            }
        }
        
        return array_slice($items, 0, 5); // 最多返回5个项目
    }
    
    /**
     * 根据分数获取等级
     */
    private function getScoreLevel($score) {
        if ($score >= 90) return '优秀';
        if ($score >= 80) return '良好';
        if ($score >= 70) return '一般';
        if ($score >= 60) return '较差';
        return '不合格';
    }
    
    /**
     * 更新订单状态
     */
    private function updateOrderStatus($orderId, $status) {
        $statusField = '';
        if ($status === 'processing') {
            $statusField = ', processing_started_at = NOW()';
        } elseif ($status === 'completed') {
            $statusField = ', completed_at = NOW()';
        }
        
        $this->db->query(
            "UPDATE analysis_orders SET status = ?{$statusField} WHERE id = ?",
            [$status, $orderId]
        );
    }
    
    /**
     * 保存分析结果
     */
    private function saveAnalysisResult($orderId, $result) {
        $this->db->query(
            "UPDATE analysis_orders SET ai_report_content = ? WHERE id = ?",
            [json_encode($result, JSON_UNESCAPED_UNICODE), $orderId]
        );
        
        // 更新用户总报告数
        $this->db->query(
            "UPDATE users SET total_reports = total_reports + 1 WHERE id = (SELECT user_id FROM analysis_orders WHERE id = ?)",
            [$orderId]
        );
    }
    
    /**
     * 发送完成通知
     */
    private function sendCompletionNotification($userId, $orderTitle) {
        try {
            // 获取用户手机号
            $user = $this->db->fetchOne(
                "SELECT phone FROM users WHERE id = ?",
                [$userId]
            );
            
            if ($user) {
                // 这里应该调用短信服务发送通知
                // 开发环境下记录日志
                error_log("分析完成通知 - 用户: {$user['phone']}, 订单: {$orderTitle}");
                
                // TODO: 集成阿里云SMS发送完成通知
                // $this->sendSMSNotification($user['phone'], $orderTitle);
            }
        } catch (Exception $e) {
            error_log("发送完成通知失败: " . $e->getMessage());
        }
    }
    
    /**
     * 记录分析错误
     */
    private function logAnalysisError($orderId, $errorMessage) {
        error_log("AI分析失败 - 订单ID: {$orderId}, 错误: {$errorMessage}");
        
        // 可以保存到专门的错误日志表
        try {
            $this->db->query(
                "INSERT INTO analysis_errors (order_id, error_message, created_at) VALUES (?, ?, NOW())",
                [$orderId, $errorMessage]
            );
        } catch (Exception $e) {
            // 忽略日志记录错误
        }
    }
    
    /**
     * 批量处理待分析订单
     */
    public function processPendingOrders($limit = 5) {
        $pendingOrders = $this->db->fetchAll(
            "SELECT id FROM analysis_orders WHERE status = 'pending' ORDER BY created_at ASC LIMIT ?",
            [$limit]
        );
        
        $results = [];
        
        foreach ($pendingOrders as $order) {
            try {
                $result = $this->analyzeOrder($order['id']);
                $results[] = [
                    'order_id' => $order['id'],
                    'status' => 'success',
                    'result' => $result
                ];
            } catch (Exception $e) {
                $results[] = [
                    'order_id' => $order['id'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * 重新分析订单
     */
    public function reanalyzeOrder($orderId) {
        // 重置订单状态
        $this->db->query(
            "UPDATE analysis_orders SET status = 'pending', ai_report_content = NULL, processing_started_at = NULL, completed_at = NULL WHERE id = ?",
            [$orderId]
        );
        
        // 执行分析
        return $this->analyzeOrder($orderId);
    }
    
    /**
     * 获取分析统计
     */
    public function getAnalysisStats() {
        return [
            'total_analyzed' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM analysis_orders WHERE status = 'completed'"
            )['count'],
            'avg_score' => $this->db->fetchOne(
                "SELECT AVG(JSON_EXTRACT(ai_report_content, '$.score')) as avg_score FROM analysis_orders WHERE status = 'completed' AND ai_report_content IS NOT NULL"
            )['avg_score'] ?? 0,
            'processing_time_avg' => $this->db->fetchOne(
                "SELECT AVG(TIMESTAMPDIFF(MINUTE, processing_started_at, completed_at)) as avg_minutes FROM analysis_orders WHERE status = 'completed' AND processing_started_at IS NOT NULL AND completed_at IS NOT NULL"
            )['avg_minutes'] ?? 0
        ];
    }
}
?>