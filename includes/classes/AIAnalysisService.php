<?php
/**
 * AI分析服务
 * 负责调用阿里云大模型和DeepSeek进行视频和话术分析
 */

class AIAnalysisService {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = new SystemConfig();
    }
    
    /**
     * 使用Qwen-Omni分析视频
     */
    public function analyzeVideoWithQwenOmni($videoPath, $analysisType = 'self') {
        try {
            $apiKey = $this->config->get('qwen_omni_api_key');
            $apiUrl = $this->config->get('qwen_omni_api_url');
            
            if (!$apiKey || !$apiUrl) {
                throw new Exception("Qwen-Omni API配置缺失");
            }
            
            // 上传视频到OSS
            $ossKey = $this->uploadVideoToOSS($videoPath);
            $videoUrl = $this->getOSSUrl($ossKey);
            
            // 构建请求数据
            $requestData = [
                'model' => 'qwen-vl-plus',
                'input' => [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'video_url',
                                    'video_url' => [
                                        'url' => $videoUrl
                                    ]
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $this->getVideoAnalysisPrompt($analysisType)
                                ]
                            ]
                        ]
                    ]
                ],
                'parameters' => [
                    'result_format' => 'message'
                ]
            ];
            
            // 调用API
            $response = $this->callQwenOmniAPI($apiUrl, $apiKey, $requestData);
            
            // 解析响应
            $analysisResult = $this->parseVideoAnalysisResponse($response, $analysisType);
            
            return [
                'success' => true,
                'result' => $analysisResult,
                'raw_response' => $response
            ];
            
        } catch (Exception $e) {
            error_log("Qwen-Omni视频分析失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 使用DeepSeek分析话术
     */
    public function analyzeScriptWithDeepSeek($scriptText, $analysisType = 'self') {
        try {
            $apiKey = $this->config->get('deepseek_api_key');
            $apiUrl = $this->config->get('deepseek_api_url');
            
            if (!$apiKey || !$apiUrl) {
                throw new Exception("DeepSeek API配置缺失");
            }
            
            // 构建请求数据
            $requestData = [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getScriptAnalysisSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->getScriptAnalysisUserPrompt($scriptText, $analysisType)
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 4000
            ];
            
            // 调用API
            $response = $this->callDeepSeekAPI($apiUrl, $apiKey, $requestData);
            
            // 解析响应
            $analysisResult = $this->parseScriptAnalysisResponse($response, $analysisType);
            
            return [
                'success' => true,
                'result' => $analysisResult,
                'raw_response' => $response
            ];
            
        } catch (Exception $e) {
            error_log("DeepSeek话术分析失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 分析多个话术并生成对比报告
     */
    public function analyzeScripts($transcripts) {
        try {
            $analysisResults = [];
            
            // 分析每个话术
            foreach ($transcripts as $transcript) {
                $analysisType = $this->getAnalysisType($transcript['video_type'], $transcript['video_index']);
                $result = $this->analyzeScriptWithDeepSeek($transcript['transcript'], $analysisType);
                
                if ($result['success']) {
                    $analysisResults[] = [
                        'video_file_id' => $transcript['video_file_id'],
                        'video_type' => $transcript['video_type'],
                        'video_index' => $transcript['video_index'],
                        'analysis_type' => $analysisType,
                        'transcript' => $transcript['transcript'],
                        'analysis' => $result['result']
                    ];
                }
            }
            
            // 生成对比分析报告
            $comparisonReport = $this->generateComparisonReport($analysisResults);
            
            return [
                'success' => true,
                'individual_analyses' => $analysisResults,
                'comparison_report' => $comparisonReport
            ];
            
        } catch (Exception $e) {
            error_log("话术对比分析失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取分析进度
     */
    public function getAnalysisProgress($videoFileId) {
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("SELECT * FROM video_files WHERE id = ?");
            $stmt->execute([$videoFileId]);
            $videoFile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$videoFile) {
                return ['completed' => false, 'progress' => 0, 'message' => '视频文件不存在'];
            }
            
            if ($videoFile['status'] === 'ai_analyzing') {
                // 检查是否有分析结果
                if ($videoFile['video_analysis_result']) {
                    return ['completed' => true, 'progress' => 100, 'message' => 'AI分析完成'];
                } else {
                    return ['completed' => false, 'progress' => 50, 'message' => 'AI分析中...'];
                }
            } elseif ($videoFile['status'] === 'ai_analysis_completed') {
                return ['completed' => true, 'progress' => 100, 'message' => 'AI分析完成'];
            }
            
            return ['completed' => false, 'progress' => 0, 'message' => '未开始分析'];
            
        } catch (Exception $e) {
            error_log("获取AI分析进度失败: " . $e->getMessage());
            return ['completed' => false, 'progress' => 0, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 调用Qwen-Omni API
     */
    private function callQwenOmniAPI($apiUrl, $apiKey, $requestData) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 300, // 5分钟超时
            CURLOPT_CONNECTTIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("API调用失败: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API返回错误: HTTP {$httpCode}, 响应: {$response}");
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("API响应解析失败: {$response}");
        }
        
        return $result;
    }
    
    /**
     * 调用DeepSeek API
     */
    private function callDeepSeekAPI($apiUrl, $apiKey, $requestData) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 120, // 2分钟超时
            CURLOPT_CONNECTTIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("API调用失败: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API返回错误: HTTP {$httpCode}, 响应: {$response}");
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("API响应解析失败: {$response}");
        }
        
        return $result;
    }
    
    /**
     * 上传视频到OSS
     */
    private function uploadVideoToOSS($videoPath) {
        $ossClient = $this->getOSSClient();
        $bucket = $this->config->get('oss_bucket');
        $ossKey = 'video_analysis/' . basename($videoPath);
        
        $result = $ossClient->uploadFile($bucket, $ossKey, $videoPath);
        
        if (!$result) {
            throw new Exception("视频上传到OSS失败");
        }
        
        return $ossKey;
    }
    
    /**
     * 获取OSS URL
     */
    private function getOSSUrl($ossKey) {
        $endpoint = $this->config->get('oss_endpoint');
        $bucket = $this->config->get('oss_bucket');
        return "https://{$bucket}.{$endpoint}/{$ossKey}";
    }
    
    /**
     * 获取OSS客户端
     */
    private function getOSSClient() {
        $accessKeyId = $this->config->get('oss_access_key');
        $accessKeySecret = $this->config->get('oss_secret_key');
        $endpoint = $this->config->get('oss_endpoint');
        
        return new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
    }
    
    /**
     * 获取视频分析提示词
     */
    private function getVideoAnalysisPrompt($analysisType) {
        $basePrompt = "请分析这个直播视频，重点关注以下方面：\n";
        $basePrompt .= "1. 主播的表现力和感染力\n";
        $basePrompt .= "2. 产品介绍的逻辑性和说服力\n";
        $basePrompt .= "3. 与观众的互动效果\n";
        $basePrompt .= "4. 直播节奏和时间把控\n";
        $basePrompt .= "5. 语言表达和话术技巧\n";
        $basePrompt .= "6. 整体直播质量评分\n\n";
        
        if ($analysisType === 'self') {
            $basePrompt .= "这是本方直播间，请重点分析优势和需要改进的地方。";
        } else {
            $basePrompt .= "这是同行直播间，请重点分析其优秀之处和可学习的地方。";
        }
        
        $basePrompt .= "\n\n请以JSON格式返回分析结果，包含：\n";
        $basePrompt .= "- overall_score: 综合评分(0-100)\n";
        $basePrompt .= "- strengths: 优势点列表\n";
        $basePrompt .= "- weaknesses: 待改进点列表\n";
        $basePrompt .= "- key_insights: 关键洞察\n";
        $basePrompt .= "- suggestions: 改进建议";
        
        return $basePrompt;
    }
    
    /**
     * 获取话术分析系统提示词
     */
    private function getScriptAnalysisSystemPrompt() {
        return "你是一个专业的直播话术分析专家，擅长分析直播带货中的话术技巧、销售策略和语言表达。请从专业角度分析提供的话术内容，识别其优点、不足和改进建议。";
    }
    
    /**
     * 获取话术分析用户提示词
     */
    private function getScriptAnalysisUserPrompt($scriptText, $analysisType) {
        $prompt = "请分析以下直播话术内容：\n\n";
        $prompt .= "【话术内容】\n{$scriptText}\n\n";
        
        if ($analysisType === 'self') {
            $prompt .= "这是本方直播间的话术，请重点分析：\n";
        } else {
            $prompt .= "这是同行直播间的话术，请重点分析：\n";
        }
        
        $prompt .= "1. 话术结构和逻辑性\n";
        $prompt .= "2. 销售技巧和说服力\n";
        $prompt .= "3. 语言表达和感染力\n";
        $prompt .= "4. 产品介绍的专业性\n";
        $prompt .= "5. 互动和引导技巧\n";
        $prompt .= "6. 整体话术质量评分\n\n";
        
        $prompt .= "请以JSON格式返回分析结果，包含：\n";
        $prompt .= "- script_score: 话术评分(0-100)\n";
        $prompt .= "- structure_analysis: 结构分析\n";
        $prompt .= "- sales_techniques: 销售技巧分析\n";
        $prompt .= "- language_quality: 语言质量分析\n";
        $prompt .= "- strengths: 优势点\n";
        $prompt .= "- improvements: 改进建议\n";
        $prompt .= "- key_phrases: 关键话术摘录";
        
        return $prompt;
    }
    
    /**
     * 解析视频分析响应
     */
    private function parseVideoAnalysisResponse($response, $analysisType) {
        if (!isset($response['output']['choices'][0]['message']['content'])) {
            throw new Exception("API响应格式错误");
        }
        
        $content = $response['output']['choices'][0]['message']['content'];
        
        // 尝试解析JSON
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonStr = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
            $result = json_decode($jsonStr, true);
            
            if ($result) {
                return $result;
            }
        }
        
        // 如果JSON解析失败，返回原始内容
        return [
            'raw_content' => $content,
            'analysis_type' => $analysisType,
            'parsed' => false
        ];
    }
    
    /**
     * 解析话术分析响应
     */
    private function parseScriptAnalysisResponse($response, $analysisType) {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("API响应格式错误");
        }
        
        $content = $response['choices'][0]['message']['content'];
        
        // 尝试解析JSON
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonStr = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
            $result = json_decode($jsonStr, true);
            
            if ($result) {
                return $result;
            }
        }
        
        // 如果JSON解析失败，返回原始内容
        return [
            'raw_content' => $content,
            'analysis_type' => $analysisType,
            'parsed' => false
        ];
    }
    
    /**
     * 生成对比分析报告
     */
    private function generateComparisonReport($analysisResults) {
        $selfAnalysis = null;
        $competitorAnalyses = [];
        
        // 分离本方和同行分析结果
        foreach ($analysisResults as $result) {
            if ($result['video_type'] === 'self') {
                $selfAnalysis = $result;
            } else {
                $competitorAnalyses[] = $result;
            }
        }
        
        if (!$selfAnalysis) {
            throw new Exception("缺少本方分析结果");
        }
        
        // 生成对比报告
        $comparisonReport = [
            'self_analysis' => $selfAnalysis,
            'competitor_analyses' => $competitorAnalyses,
            'comparison_summary' => $this->generateComparisonSummary($selfAnalysis, $competitorAnalyses),
            'learning_suggestions' => $this->generateLearningSuggestions($selfAnalysis, $competitorAnalyses),
            'overall_score' => $this->calculateOverallScore($selfAnalysis, $competitorAnalyses)
        ];
        
        return $comparisonReport;
    }
    
    /**
     * 生成对比总结
     */
    private function generateComparisonSummary($selfAnalysis, $competitorAnalyses) {
        $summary = "对比分析总结：\n\n";
        
        // 本方表现
        $summary .= "本方表现：\n";
        if (isset($selfAnalysis['analysis']['overall_score'])) {
            $summary .= "- 综合评分：{$selfAnalysis['analysis']['overall_score']}/100\n";
        }
        if (isset($selfAnalysis['analysis']['strengths'])) {
            $summary .= "- 主要优势：" . implode('、', $selfAnalysis['analysis']['strengths']) . "\n";
        }
        if (isset($selfAnalysis['analysis']['weaknesses'])) {
            $summary .= "- 待改进：" . implode('、', $selfAnalysis['analysis']['weaknesses']) . "\n";
        }
        
        $summary .= "\n同行对比：\n";
        foreach ($competitorAnalyses as $index => $competitor) {
            $summary .= "同行" . ($index + 1) . "：";
            if (isset($competitor['analysis']['overall_score'])) {
                $summary .= "评分{$competitor['analysis']['overall_score']}/100";
            }
            $summary .= "\n";
        }
        
        return $summary;
    }
    
    /**
     * 生成学习建议
     */
    private function generateLearningSuggestions($selfAnalysis, $competitorAnalyses) {
        $suggestions = [];
        
        foreach ($competitorAnalyses as $competitor) {
            if (isset($competitor['analysis']['strengths'])) {
                foreach ($competitor['analysis']['strengths'] as $strength) {
                    $suggestions[] = "学习同行优势：{$strength}";
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * 计算总体评分
     */
    private function calculateOverallScore($selfAnalysis, $competitorAnalyses) {
        $selfScore = $selfAnalysis['analysis']['overall_score'] ?? 0;
        $competitorScores = [];
        
        foreach ($competitorAnalyses as $competitor) {
            if (isset($competitor['analysis']['overall_score'])) {
                $competitorScores[] = $competitor['analysis']['overall_score'];
            }
        }
        
        $avgCompetitorScore = !empty($competitorScores) ? array_sum($competitorScores) / count($competitorScores) : 0;
        
        return [
            'self_score' => $selfScore,
            'competitor_avg_score' => $avgCompetitorScore,
            'gap' => $selfScore - $avgCompetitorScore
        ];
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
}
?>
