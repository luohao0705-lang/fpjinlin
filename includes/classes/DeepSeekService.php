<?php
/**
 * DeepSeek AI服务类
 * 复盘精灵系统
 */

class DeepSeekService {
    private $db;
    private $apiKey;
    private $apiUrl;
    
    public function __construct() {
        $this->db = new Database();
        $this->loadConfig();
    }
    
    /**
     * 加载配置
     */
    private function loadConfig() {
        // 优先从环境变量读取
        $this->apiKey = EnvLoader::get('DEEPSEEK_API_KEY');
        $this->apiUrl = EnvLoader::get('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions');
        
        // 如果环境变量没有配置，则从数据库读取
        if (empty($this->apiKey)) {
            $this->apiKey = getSystemConfig('deepseek_api_key', '');
        }
        if (empty($this->apiUrl)) {
            $this->apiUrl = getSystemConfig('deepseek_api_url', 'https://api.deepseek.com/v1/chat/completions');
        }
    }
    
    /**
     * 生成分析报告
     */
    public function generateAnalysisReport($screenshots, $coverImage, $selfScript, $competitorScripts) {
        error_log("DeepSeek分析开始 - 检查API密钥配置");
        
        if (empty($this->apiKey)) {
            error_log("DeepSeek API密钥未配置");
            throw new Exception('DeepSeek API密钥未配置');
        }
        
        error_log("DeepSeek API密钥已配置，开始构建分析提示词");
        
        // 构建分析提示词
        $prompt = $this->buildAnalysisPrompt($selfScript, $competitorScripts);
        error_log("分析提示词构建完成，长度: " . strlen($prompt));
        
        // 调用DeepSeek API
        error_log("开始调用DeepSeek API: " . $this->apiUrl);
        $response = $this->callDeepSeekAPI($prompt);
        error_log("DeepSeek API调用完成，开始解析响应");
        
        // 解析响应
        $result = $this->parseAnalysisResponse($response);
        error_log("DeepSeek分析报告生成完成");
        
        return $result;
    }
    
    /**
     * 构建分析提示词
     */
    private function buildAnalysisPrompt($selfScript, $competitorScripts) {
        $prompt = "你是一位专业的直播带货分析师，请根据以下信息进行深入的直播复盘分析：\n\n";
        
        $prompt .= "## 本方直播话术：\n";
        $prompt .= $selfScript . "\n\n";
        
        $prompt .= "## 同行直播话术：\n";
        foreach ($competitorScripts as $index => $script) {
            $prompt .= "### 同行" . ($index + 1) . "：\n";
            $prompt .= $script . "\n\n";
        }
        
        $prompt .= "## 分析要求：\n";
        $prompt .= "请按照以下结构进行专业分析，并给出详细的改进建议：\n\n";
        
        $prompt .= "### 1. 综合评分 (0-100分)\n";
        $prompt .= "- 根据话术质量、逻辑性、吸引力等维度综合评分\n";
        $prompt .= "- 评分标准：90-100优秀，80-89良好，70-79一般，60-69较差，60以下不合格\n\n";
        
        $prompt .= "### 2. 等级评定\n";
        $prompt .= "- 优秀(excellent)：90-100分\n";
        $prompt .= "- 良好(good)：80-89分\n";
        $prompt .= "- 一般(average)：70-79分\n";
        $prompt .= "- 较差(poor)：60-69分\n";
        $prompt .= "- 不合格(unqualified)：60分以下\n\n";
        
        $prompt .= "### 3. 本方分析\n";
        $prompt .= "- 话术优势分析\n";
        $prompt .= "- 存在问题识别\n";
        $prompt .= "- 逻辑结构评价\n";
        $prompt .= "- 情感调动效果\n\n";
        
        $prompt .= "### 4. 同行对比\n";
        $prompt .= "- 与各同行的优劣势对比\n";
        $prompt .= "- 学习借鉴点\n";
        $prompt .= "- 差异化优势\n\n";
        
        $prompt .= "### 5. 改进方案\n";
        $prompt .= "- 具体的话术优化建议\n";
        $prompt .= "- 结构调整方案\n";
        $prompt .= "- 情感表达改进\n";
        $prompt .= "- 转化率提升策略\n\n";
        
        $prompt .= "请确保分析专业、深入、可操作，提供的建议要具体且实用。\n";
        $prompt .= "请以JSON格式返回结果，包含score(数字)、level(字符串)、report(完整分析报告HTML格式)三个字段。";
        
        return $prompt;
    }
    
    /**
     * 调用DeepSeek API
     */
    private function callDeepSeekAPI($prompt) {
        $data = [
            'model' => 'deepseek-chat',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是一位专业的直播带货分析师，擅长分析直播话术的优劣势并提供改进建议。'
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
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, DEEPSEEK_API_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("API请求失败: {$error}");
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("API请求失败，HTTP状态码: {$httpCode}");
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            throw new Exception('API响应格式错误');
        }
        
        return $result['choices'][0]['message']['content'];
    }
    
    /**
     * 解析分析响应
     */
    private function parseAnalysisResponse($response) {
        // 尝试解析JSON响应
        $decoded = json_decode($response, true);
        
        if ($decoded && isset($decoded['score']) && isset($decoded['level']) && isset($decoded['report'])) {
            return [
                'score' => (int)$decoded['score'],
                'level' => $decoded['level'],
                'report' => $decoded['report']
            ];
        }
        
        // 如果不是JSON格式，尝试从文本中提取信息
        return $this->parseTextResponse($response);
    }
    
    /**
     * 解析文本响应
     */
    private function parseTextResponse($response) {
        $score = 75; // 默认分数
        $level = 'average'; // 默认等级
        
        // 尝试从文本中提取分数
        if (preg_match('/(\d+)分/', $response, $matches)) {
            $score = (int)$matches[1];
        } elseif (preg_match('/评分[：:]\s*(\d+)/', $response, $matches)) {
            $score = (int)$matches[1];
        }
        
        // 根据分数确定等级
        if ($score >= 90) {
            $level = 'excellent';
        } elseif ($score >= 80) {
            $level = 'good';
        } elseif ($score >= 70) {
            $level = 'average';
        } elseif ($score >= 60) {
            $level = 'poor';
        } else {
            $level = 'unqualified';
        }
        
        // 格式化报告为HTML
        $reportHtml = $this->formatReportAsHtml($response);
        
        return [
            'score' => $score,
            'level' => $level,
            'report' => $reportHtml
        ];
    }
    
    /**
     * 将文本报告格式化为HTML
     */
    private function formatReportAsHtml($text) {
        // 基础HTML格式化
        $html = '<div class="analysis-report">';
        
        // 替换标题
        $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);
        
        // 替换列表
        $text = preg_replace('/^- (.+)$/m', '<li>$1</li>', $text);
        $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);
        
        // 替换段落
        $paragraphs = explode("\n\n", $text);
        foreach ($paragraphs as $paragraph) {
            if (!empty(trim($paragraph)) && !preg_match('/^<[h1-6ul]/', trim($paragraph))) {
                $html .= '<p>' . trim($paragraph) . '</p>';
            } else {
                $html .= trim($paragraph);
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 测试API连接
     */
    public function testConnection() {
        if (empty($this->apiKey)) {
            throw new Exception('API密钥未配置');
        }
        
        try {
            $response = $this->callDeepSeekAPI('你好，请回复"连接正常"');
            return ['success' => true, 'message' => 'API连接正常'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}