<?php
/**
 * 阿里云Qwen-Omni视频理解服务类
 * 复盘精灵系统 - 视频内容分析
 */

class QwenOmniService {
    private $db;
    private $apiKey;
    private $apiUrl;
    private $config;
    
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
        $this->apiKey = getSystemConfig('qwen_omni_api_key', '');
        $this->apiUrl = getSystemConfig('qwen_omni_api_url', 'https://dashscope.aliyuncs.com/api/v1/services/aigc/video-understanding/generation');
        
        $this->config = [
            'api_key' => $this->apiKey,
            'api_url' => $this->apiUrl,
            'timeout' => 60,
            'max_retries' => 3
        ];
    }
    
    /**
     * 分析视频切片
     */
    public function analyzeSegment($segmentId) {
        try {
            error_log("开始视频理解分析: 切片ID {$segmentId}");
            
            // 获取切片信息
            $segment = $this->db->fetchOne(
                "SELECT vs.*, vf.order_id, vf.video_type, vf.video_index 
                 FROM video_segments vs 
                 LEFT JOIN video_files vf ON vs.video_file_id = vf.id 
                 WHERE vs.id = ?",
                [$segmentId]
            );
            
            if (!$segment || !$segment['oss_key']) {
                throw new Exception('切片不存在或文件未准备好');
            }
            
            // 获取视频文件URL
            $videoUrl = $this->getVideoUrl($segment['oss_key']);
            
            // 构建分析提示词
            $prompt = $this->buildAnalysisPrompt($segment);
            
            // 调用Qwen-Omni API
            $analysisResult = $this->callQwenOmniAPI($videoUrl, $prompt);
            
            // 保存分析结果
            $this->saveAnalysisResults($segmentId, $analysisResult);
            
            error_log("视频理解分析完成: 切片ID {$segmentId}");
            return true;
            
        } catch (Exception $e) {
            error_log("视频理解分析失败: 切片ID {$segmentId} - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 构建分析提示词
     */
    private function buildAnalysisPrompt($segment) {
        $videoType = $segment['video_type'] === 'self' ? '本方' : '同行' . $segment['video_index'];
        $timeRange = "第" . ($segment['segment_index'] + 1) . "段 (" . $this->formatTime($segment['start_time']) . "-" . $this->formatTime($segment['end_time']) . ")";
        
        $prompt = "请分析这个{$videoType}直播视频片段{$timeRange}，重点关注以下方面：\n\n";
        
        $prompt .= "## 分析要求\n\n";
        
        $prompt .= "### 1. 主播表现分析\n";
        $prompt .= "- 情绪状态：开心、紧张、自信、疲惫等\n";
        $prompt .= "- 语速节奏：快、中、慢，是否有停顿\n";
        $prompt .= "- 肢体动作：手势、表情、姿态\n";
        $prompt .= "- 镜头感：是否看镜头，与观众互动\n\n";
        
        $prompt .= "### 2. 话术内容分析\n";
        $prompt .= "- 开场话术：如何吸引注意力\n";
        $prompt .= "- 卖点介绍：产品特点、优势\n";
        $prompt .= "- 价格策略：定价、优惠、限时\n";
        $prompt .= "- 互动引导：提问、点赞、关注\n";
        $prompt .= "- 收尾话术：下单引导、感谢\n\n";
        
        $prompt .= "### 3. 商品演示分析\n";
        $prompt .= "- 演示方式：实物展示、功能演示\n";
        $prompt .= "- 镜头配合：特写、全景、多角度\n";
        $prompt .= "- 证据化：数据、对比、证明\n";
        $prompt .= "- 演示完整性：是否充分展示产品\n\n";
        
        $prompt .= "### 4. 场景氛围分析\n";
        $prompt .= "- 灯光效果：明亮度、色温\n";
        $prompt .= "- 背景布置：产品摆放、装饰\n";
        $prompt .= "- 音效：背景音乐、音质\n";
        $prompt .= "- 整体氛围：专业、亲民、高端等\n\n";
        
        $prompt .= "### 5. 文字信息提取\n";
        $prompt .= "- 价格信息：原价、现价、优惠\n";
        $prompt .= "- 产品参数：规格、材质、功能\n";
        $prompt .= "- 活动信息：限时、限量、福利\n";
        $prompt .= "- 联系方式：微信、电话、店铺\n\n";
        
        $prompt .= "### 6. 风险合规检查\n";
        $prompt .= "- 敏感词汇：夸大宣传、绝对化用语\n";
        $prompt .= "- 违规内容：虚假宣传、误导消费者\n";
        $prompt .= "- 改进建议：如何合规表达\n\n";
        
        $prompt .= "请以JSON格式返回分析结果，包含以上所有维度的详细分析。";
        
        return $prompt;
    }
    
    /**
     * 调用Qwen-Omni API
     */
    private function callQwenOmniAPI($videoUrl, $prompt) {
        if (empty($this->config['api_key'])) {
            throw new Exception('Qwen-Omni API密钥未配置');
        }
        
        $requestData = [
            'model' => 'qwen-vl-plus',
            'input' => [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt
                            ],
                            [
                                'type' => 'video_url',
                                'video_url' => [
                                    'url' => $videoUrl
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'parameters' => [
                'result_format' => 'message',
                'incremental_output' => false
            ]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->config['api_key'],
            'Content-Type: application/json',
            'X-DashScope-Async: enable'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['api_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('API请求失败: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('API请求失败，HTTP状态码: ' . $httpCode . ', 响应: ' . $response);
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception('API响应解析失败');
        }
        
        if (isset($result['error'])) {
            throw new Exception('API返回错误: ' . $result['error']['message']);
        }
        
        return $result;
    }
    
    /**
     * 保存分析结果
     */
    private function saveAnalysisResults($segmentId, $analysisResult) {
        // 解析API响应
        $content = $analysisResult['output']['choices'][0]['message']['content'] ?? '';
        
        // 尝试解析JSON内容
        $analysisData = json_decode($content, true);
        if (!$analysisData) {
            // 如果不是JSON，保存原始文本
            $analysisData = ['raw_content' => $content];
        }
        
        // 保存到数据库
        $this->db->insert(
            "INSERT INTO video_analysis_results (segment_id, analysis_type, result_data, confidence, created_at) VALUES (?, 'comprehensive', ?, 0.8, NOW())",
            [$segmentId, json_encode($analysisData, JSON_UNESCAPED_UNICODE)]
        );
    }
    
    /**
     * 获取订单的完整分析结果
     */
    public function getOrderAnalysisResults($orderId) {
        $results = $this->db->fetchAll(
            "SELECT var.*, vs.segment_index, vs.start_time as segment_start, vs.end_time as segment_end,
                    vf.video_type, vf.video_index
             FROM video_analysis_results var
             LEFT JOIN video_segments vs ON var.segment_id = vs.id
             LEFT JOIN video_files vf ON vs.video_file_id = vf.id
             WHERE vf.order_id = ? AND var.analysis_type = 'comprehensive'
             ORDER BY vf.video_type, vf.video_index, vs.segment_index",
            [$orderId]
        );
        
        return $results;
    }
    
    /**
     * 生成综合分析报告
     */
    public function generateComprehensiveReport($orderId) {
        $results = $this->getOrderAnalysisResults($orderId);
        
        $report = [
            'order_id' => $orderId,
            'analysis_time' => date('Y-m-d H:i:s'),
            'segments' => [],
            'summary' => [
                'total_segments' => count($results),
                'self_segments' => 0,
                'competitor_segments' => 0
            ]
        ];
        
        foreach ($results as $result) {
            $data = json_decode($result['result_data'], true);
            $segmentInfo = [
                'segment_index' => $result['segment_index'],
                'time_range' => $this->formatTime($result['segment_start']) . '-' . $this->formatTime($result['segment_end']),
                'video_type' => $result['video_type'],
                'video_index' => $result['video_index'],
                'analysis' => $data
            ];
            
            $report['segments'][] = $segmentInfo;
            
            if ($result['video_type'] === 'self') {
                $report['summary']['self_segments']++;
            } else {
                $report['summary']['competitor_segments']++;
            }
        }
        
        return $report;
    }
    
    /**
     * 获取视频文件URL
     */
    private function getVideoUrl($ossKey) {
        // 这里需要实现OSS URL生成逻辑
        // 临时实现：直接返回OSS键
        return $ossKey;
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
    
    /**
     * 检查API配置
     */
    public function checkApiConfig() {
        return !empty($this->config['api_key']);
    }
    
    /**
     * 测试API连接
     */
    public function testApiConnection() {
        try {
            $testData = [
                'model' => 'qwen-vl-plus',
                'input' => [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => '请简单分析这个视频'
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            
            $headers = [
                'Authorization: Bearer ' . $this->config['api_key'],
                'Content-Type: application/json'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->config['api_url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
            
        } catch (Exception $e) {
            return false;
        }
    }
}
