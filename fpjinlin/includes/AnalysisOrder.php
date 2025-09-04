<?php
/**
 * 分析订单管理类
 * 复盘精灵系统
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/User.php';

class AnalysisOrder {
    private $db;
    private $user;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->user = new User();
    }
    
    /**
     * 创建分析订单
     */
    public function createOrder($userId, $title, $ownScript, $competitorScripts = []) {
        $this->db->beginTransaction();
        
        try {
            // 检查精灵币余额
            $costCoins = getSystemConfig('analysis_cost_coins', 100);
            if (!$this->user->checkCoinsBalance($userId, $costCoins)) {
                throw new Exception('精灵币余额不足，请先充值');
            }
            
            // 生成订单号
            $orderNo = generateOrderNo();
            
            // 创建订单
            $this->db->query(
                "INSERT INTO analysis_orders (user_id, order_no, title, own_script, competitor1_script, competitor2_script, competitor3_script, cost_coins, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                [
                    $userId, 
                    $orderNo, 
                    $title, 
                    $ownScript,
                    $competitorScripts[0] ?? '',
                    $competitorScripts[1] ?? '',
                    $competitorScripts[2] ?? '',
                    $costCoins
                ]
            );
            
            $orderId = $this->db->lastInsertId();
            
            // 扣除精灵币
            $this->user->consumeCoins($userId, $costCoins, $orderId, "分析订单：{$title}");
            
            $this->db->commit();
            
            return $this->getOrderById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 上传订单截图
     */
    public function uploadScreenshots($orderId, $files) {
        $order = $this->getOrderById($orderId);
        if (!$order) {
            throw new Exception('订单不存在');
        }
        
        if ($order['status'] !== 'pending') {
            throw new Exception('订单状态不允许上传文件');
        }
        
        $uploadedFiles = [];
        $imageTypes = ['data1', 'data2', 'data3', 'data4', 'data5'];
        
        foreach ($files as $type => $file) {
            if (!in_array($type, $imageTypes)) {
                continue;
            }
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("文件上传失败: {$type}");
            }
            
            // 验证文件类型
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExt, ALLOWED_IMAGE_TYPES)) {
                throw new Exception("不支持的文件类型: {$fileExt}");
            }
            
            // 验证文件大小
            if ($file['size'] > UPLOAD_MAX_SIZE) {
                throw new Exception("文件大小超过限制: " . formatFileSize(UPLOAD_MAX_SIZE));
            }
            
            // 生成文件名
            $fileName = $orderId . '_' . $type . '_' . time() . '.' . $fileExt;
            $uploadPath = UPLOAD_PATH . 'screenshots/' . $fileName;
            
            // 确保目录存在
            $uploadDir = dirname($uploadPath);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 移动文件
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception("文件保存失败: {$type}");
            }
            
            // 保存到数据库
            $this->db->query(
                "INSERT INTO order_screenshots (order_id, image_path, image_type, file_size) VALUES (?, ?, ?, ?)",
                [$orderId, 'screenshots/' . $fileName, $type, $file['size']]
            );
            
            $uploadedFiles[$type] = 'screenshots/' . $fileName;
        }
        
        return $uploadedFiles;
    }
    
    /**
     * 上传封面图片
     */
    public function uploadCover($orderId, $file) {
        $order = $this->getOrderById($orderId);
        if (!$order) {
            throw new Exception('订单不存在');
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("封面图片上传失败");
        }
        
        // 验证文件类型
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, ALLOWED_IMAGE_TYPES)) {
            throw new Exception("不支持的图片类型: {$fileExt}");
        }
        
        // 验证文件大小
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            throw new Exception("文件大小超过限制: " . formatFileSize(UPLOAD_MAX_SIZE));
        }
        
        // 生成文件名
        $fileName = $orderId . '_cover_' . time() . '.' . $fileExt;
        $uploadPath = UPLOAD_PATH . 'covers/' . $fileName;
        
        // 确保目录存在
        $uploadDir = dirname($uploadPath);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception("封面图片保存失败");
        }
        
        // 更新订单
        $this->db->query(
            "UPDATE analysis_orders SET cover_image = ? WHERE id = ?",
            ['covers/' . $fileName, $orderId]
        );
        
        return 'covers/' . $fileName;
    }
    
    /**
     * 提交订单进行AI分析
     */
    public function submitForAnalysis($orderId) {
        $order = $this->getOrderById($orderId);
        if (!$order) {
            throw new Exception('订单不存在');
        }
        
        if ($order['status'] !== 'pending') {
            throw new Exception('订单状态不允许提交分析');
        }
        
        // 检查必要文件是否上传完成
        $screenshots = $this->getOrderScreenshots($orderId);
        if (count($screenshots) < 5) {
            throw new Exception('请上传完整的5张截图');
        }
        
        if (empty($order['own_script'])) {
            throw new Exception('请填写本方话术');
        }
        
        // 更新订单状态
        $this->db->query(
            "UPDATE analysis_orders SET status = 'processing', processing_started_at = NOW() WHERE id = ?",
            [$orderId]
        );
        
        // 这里应该触发后台AI分析任务
        // 可以使用队列系统或者直接调用分析函数
        $this->processAIAnalysis($orderId);
        
        return true;
    }
    
    /**
     * AI分析处理（异步或同步）
     */
    public function processAIAnalysis($orderId) {
        try {
            $order = $this->getOrderById($orderId);
            $screenshots = $this->getOrderScreenshots($orderId);
            
            // 构建AI分析提示词
            $prompt = $this->buildAnalysisPrompt($order, $screenshots);
            
            // 调用DeepSeek API
            $analysisResult = $this->callDeepSeekAPI($prompt);
            
            // 保存分析结果
            $this->db->query(
                "UPDATE analysis_orders SET ai_report_content = ?, status = 'completed', completed_at = NOW() WHERE id = ?",
                [json_encode($analysisResult, JSON_UNESCAPED_UNICODE), $orderId]
            );
            
            // 更新用户报告总数
            $this->db->query(
                "UPDATE users SET total_reports = total_reports + 1 WHERE id = ?",
                [$order['user_id']]
            );
            
            // 发送完成通知短信
            $this->sendCompletionSMS($order['user_id'], $order['title']);
            
            return true;
            
        } catch (Exception $e) {
            // 分析失败，更新状态并退还精灵币
            $this->db->query(
                "UPDATE analysis_orders SET status = 'failed' WHERE id = ?",
                [$orderId]
            );
            
            // 退还精灵币
            $this->refundCoins($orderId);
            
            error_log("AI分析失败 - 订单ID: {$orderId}, 错误: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 构建AI分析提示词
     */
    private function buildAnalysisPrompt($order, $screenshots) {
        $prompt = "请作为专业的直播带货分析师，对以下直播数据进行深度分析：\n\n";
        $prompt .= "**分析标题**: {$order['title']}\n\n";
        $prompt .= "**本方话术**:\n{$order['own_script']}\n\n";
        
        if (!empty($order['competitor1_script'])) {
            $prompt .= "**同行1话术**:\n{$order['competitor1_script']}\n\n";
        }
        if (!empty($order['competitor2_script'])) {
            $prompt .= "**同行2话术**:\n{$order['competitor2_script']}\n\n";
        }
        if (!empty($order['competitor3_script'])) {
            $prompt .= "**同行3话术**:\n{$order['competitor3_script']}\n\n";
        }
        
        $prompt .= "**分析要求**:\n";
        $prompt .= "1. 综合评分（0-100分）和等级划分（优秀90-100/良好80-89/一般70-79/较差60-69/不合格0-59）\n";
        $prompt .= "2. 本方话术深度分析（优势、不足、话术结构、情感调动、转化技巧等）\n";
        $prompt .= "3. 同行对比分析（话术差异、优劣势对比、学习点）\n";
        $prompt .= "4. 具体改进方案和话术优化建议\n";
        $prompt .= "5. 数据分析（基于上传的截图数据）\n\n";
        $prompt .= "请生成专业、详细的分析报告，格式为JSON结构，包含score、level、analysis、comparison、suggestions等字段。";
        
        return $prompt;
    }
    
    /**
     * 调用DeepSeek API
     */
    private function callDeepSeekAPI($prompt) {
        $apiKey = getSystemConfig('deepseek_api_key');
        $apiUrl = getSystemConfig('deepseek_api_url', DEEPSEEK_API_URL);
        
        if (empty($apiKey)) {
            throw new Exception('DeepSeek API密钥未配置');
        }
        
        $data = [
            'model' => DEEPSEEK_MODEL,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 4000
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("API调用失败: {$curlError}");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API返回错误: HTTP {$httpCode}");
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            throw new Exception("API响应格式错误");
        }
        
        $content = $result['choices'][0]['message']['content'];
        
        // 尝试解析JSON响应
        $analysisData = json_decode($content, true);
        if (!$analysisData) {
            // 如果不是JSON格式，包装成标准格式
            $analysisData = [
                'score' => 0,
                'level' => '待评估',
                'analysis' => $content,
                'comparison' => '',
                'suggestions' => '',
                'raw_content' => $content
            ];
        }
        
        return $analysisData;
    }
    
    /**
     * 发送报告完成短信通知
     */
    private function sendCompletionSMS($userId, $orderTitle) {
        try {
            $user = $this->user->getUserById($userId);
            if ($user) {
                // 这里应该调用短信发送，暂时记录日志
                error_log("报告完成通知 - 用户: {$user['phone']}, 订单: {$orderTitle}");
                // TODO: 实现真实的短信发送
            }
        } catch (Exception $e) {
            error_log("发送完成通知失败: " . $e->getMessage());
        }
    }
    
    /**
     * 退还精灵币
     */
    private function refundCoins($orderId) {
        try {
            $order = $this->getOrderById($orderId);
            if ($order && $order['cost_coins'] > 0) {
                // 退还精灵币
                $this->db->query(
                    "UPDATE users SET spirit_coins = spirit_coins + ? WHERE id = ?",
                    [$order['cost_coins'], $order['user_id']]
                );
                
                // 记录退款交易
                $newBalance = $this->user->getUserById($order['user_id'])['spirit_coins'];
                $this->db->query(
                    "INSERT INTO coin_transactions (user_id, transaction_type, amount, balance_after, related_order_id, description) VALUES (?, 'refund', ?, ?, ?, ?)",
                    [$order['user_id'], $order['cost_coins'], $newBalance, $orderId, "订单失败退款：{$order['title']}"]
                );
            }
        } catch (Exception $e) {
            error_log("退还精灵币失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取订单详情
     */
    public function getOrderById($orderId) {
        return $this->db->fetchOne(
            "SELECT * FROM analysis_orders WHERE id = ?",
            [$orderId]
        );
    }
    
    /**
     * 获取订单截图
     */
    public function getOrderScreenshots($orderId) {
        return $this->db->fetchAll(
            "SELECT * FROM order_screenshots WHERE order_id = ? ORDER BY image_type",
            [$orderId]
        );
    }
    
    /**
     * 获取用户的订单详情（验证权限）
     */
    public function getUserOrder($userId, $orderId) {
        return $this->db->fetchOne(
            "SELECT * FROM analysis_orders WHERE id = ? AND user_id = ?",
            [$orderId, $userId]
        );
    }
    
    /**
     * 生成分享链接
     */
    public function generateShareLink($orderId, $userId) {
        $order = $this->getUserOrder($userId, $orderId);
        if (!$order) {
            throw new Exception('订单不存在或无权限访问');
        }
        
        if ($order['status'] !== 'completed') {
            throw new Exception('报告尚未完成');
        }
        
        // 生成分享token
        $shareToken = md5($orderId . $userId . time());
        
        return SITE_URL . "/pages/user/report_share.php?token=" . $shareToken . "&order=" . $orderId;
    }
    
    /**
     * 获取所有订单（管理员用）
     */
    public function getAllOrders($page = 1, $pageSize = ADMIN_PAGE_SIZE, $filters = []) {
        $where = "1=1";
        $params = [];
        
        // 状态筛选
        if (!empty($filters['status'])) {
            $where .= " AND ao.status = ?";
            $params[] = $filters['status'];
        }
        
        // 用户筛选
        if (!empty($filters['user_phone'])) {
            $where .= " AND u.phone LIKE ?";
            $params[] = '%' . $filters['user_phone'] . '%';
        }
        
        // 时间范围筛选
        if (!empty($filters['start_date'])) {
            $where .= " AND ao.created_at >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $where .= " AND ao.created_at <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }
        
        $offset = ($page - 1) * $pageSize;
        
        // 获取订单列表
        $orders = $this->db->fetchAll(
            "SELECT ao.*, u.phone, u.nickname 
             FROM analysis_orders ao 
             LEFT JOIN users u ON ao.user_id = u.id 
             WHERE {$where} 
             ORDER BY ao.created_at DESC 
             LIMIT ? OFFSET ?",
            array_merge($params, [$pageSize, $offset])
        );
        
        // 获取总数
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count 
             FROM analysis_orders ao 
             LEFT JOIN users u ON ao.user_id = u.id 
             WHERE {$where}",
            $params
        )['count'];
        
        return [
            'orders' => $orders,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize)
        ];
    }
    
    /**
     * 获取统计数据
     */
    public function getStatistics() {
        $stats = [];
        
        // 总订单数
        $stats['total_orders'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM analysis_orders"
        )['count'];
        
        // 今日订单数
        $stats['today_orders'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM analysis_orders WHERE DATE(created_at) = CURDATE()"
        )['count'];
        
        // 待处理订单数
        $stats['pending_orders'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM analysis_orders WHERE status IN ('pending', 'processing')"
        )['count'];
        
        // 完成率
        $completedOrders = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM analysis_orders WHERE status = 'completed'"
        )['count'];
        
        $stats['completion_rate'] = $stats['total_orders'] > 0 
            ? round($completedOrders / $stats['total_orders'] * 100, 2) 
            : 0;
        
        return $stats;
    }
}
?>