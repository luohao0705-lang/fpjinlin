<?php
/**
 * 分析订单类
 * 复盘精灵系统
 */

class AnalysisOrder {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * 创建分析订单
     */
    public function createOrder($userId, $title, $liveScreenshots, $coverImage, $selfScript, $competitorScripts) {
        $this->db->beginTransaction();
        
        try {
            // 检查用户精灵币余额
            $user = new User();
            $costCoins = $this->getAnalysisCost();
            
            if (!$user->checkCoinBalance($userId, $costCoins)) {
                throw new Exception('精灵币余额不足，无法创建分析订单');
            }
            
            // 生成订单号
            $orderNo = generateOrderNo();
            
            // 创建订单
            $orderId = $this->db->insert(
                "INSERT INTO analysis_orders (user_id, order_no, title, cost_coins, live_screenshots, cover_image, self_script, competitor_scripts, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                [
                    $userId, 
                    $orderNo, 
                    $title, 
                    $costCoins,
                    json_encode($liveScreenshots, JSON_UNESCAPED_UNICODE),
                    $coverImage,
                    $selfScript,
                    json_encode($competitorScripts, JSON_UNESCAPED_UNICODE)
                ]
            );
            
            // 扣除精灵币
            $user->consumeCoins($userId, $costCoins, $orderId, "创建分析订单：{$title}");
            
            $this->db->commit();
            
            // 异步处理AI分析
            $this->processAnalysisAsync($orderId);
            
            return [
                'orderId' => $orderId,
                'orderNo' => $orderNo,
                'costCoins' => $costCoins
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 获取分析成本
     */
    private function getAnalysisCost() {
        $config = $this->db->fetchOne(
            "SELECT config_value FROM system_configs WHERE config_key = 'default_coin_cost'"
        );
        
        return $config ? (int)$config['config_value'] : DEFAULT_ANALYSIS_COST;
    }
    
    /**
     * 异步处理AI分析
     */
    private function processAnalysisAsync($orderId) {
        // 在实际生产环境中，这里应该使用队列系统（如Redis Queue）
        // 为了简化，这里使用后台进程处理
        $command = "php " . BASE_PATH . "/scripts/process_analysis.php {$orderId} > /dev/null 2>&1 &";
        exec($command);
    }
    
    /**
     * 处理AI分析
     */
    public function processAnalysis($orderId) {
        try {
            // 更新订单状态为处理中
            $this->updateOrderStatus($orderId, 'processing');
            
            // 获取订单数据
            $order = $this->getOrderById($orderId);
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            // 调用AI服务生成报告
            $aiService = new DeepSeekService();
            $reportData = $aiService->generateAnalysisReport(
                json_decode($order['live_screenshots'], true),
                $order['cover_image'],
                $order['self_script'],
                json_decode($order['competitor_scripts'], true)
            );
            
            // 保存报告结果
            $this->db->query(
                "UPDATE analysis_orders SET 
                 status = 'completed', 
                 ai_report = ?, 
                 report_score = ?, 
                 report_level = ?, 
                 completed_at = NOW() 
                 WHERE id = ?",
                [
                    $reportData['report'],
                    $reportData['score'],
                    $reportData['level'],
                    $orderId
                ]
            );
            
            // 发送完成通知短信
            $this->sendCompletionNotification($order['user_id'], $order['title']);
            
            return true;
            
        } catch (Exception $e) {
            // 更新订单状态为失败
            $this->updateOrderStatus($orderId, 'failed', $e->getMessage());
            error_log("AI分析失败 - 订单ID: {$orderId}, 错误: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新订单状态
     */
    public function updateOrderStatus($orderId, $status, $errorMessage = null) {
        if ($errorMessage) {
            $this->db->query(
                "UPDATE analysis_orders SET status = ?, error_message = ?, updated_at = NOW() WHERE id = ?",
                [$status, $errorMessage, $orderId]
            );
        } else {
            $this->db->query(
                "UPDATE analysis_orders SET status = ?, updated_at = NOW() WHERE id = ?",
                [$status, $orderId]
            );
        }
    }
    
    /**
     * 获取订单详情
     */
    public function getOrderById($orderId) {
        return $this->db->fetchOne(
            "SELECT ao.*, u.phone, u.nickname FROM analysis_orders ao 
             LEFT JOIN users u ON ao.user_id = u.id 
             WHERE ao.id = ?",
            [$orderId]
        );
    }
    
    /**
     * 获取用户订单列表
     */
    public function getUserOrders($userId, $page = 1, $pageSize = 20) {
        $offset = ($page - 1) * $pageSize;
        
        $orders = $this->db->fetchAll(
            "SELECT id, order_no, title, status, cost_coins, report_score, report_level, created_at, completed_at 
             FROM analysis_orders 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$userId, $pageSize, $offset]
        );
        
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM analysis_orders WHERE user_id = ?",
            [$userId]
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
     * 获取所有订单（管理员）
     */
    public function getAllOrders($page = 1, $pageSize = 20, $status = null) {
        $offset = ($page - 1) * $pageSize;
        $whereClause = '';
        $params = [];
        
        if ($status) {
            $whereClause = ' WHERE ao.status = ?';
            $params[] = $status;
        }
        
        $params = array_merge($params, [$pageSize, $offset]);
        
        $orders = $this->db->fetchAll(
            "SELECT ao.*, u.phone, u.nickname 
             FROM analysis_orders ao 
             LEFT JOIN users u ON ao.user_id = u.id 
             {$whereClause}
             ORDER BY ao.created_at DESC 
             LIMIT ? OFFSET ?",
            $params
        );
        
        $countParams = $status ? [$status] : [];
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM analysis_orders ao {$whereClause}",
            $countParams
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
     * 发送完成通知短信
     */
    private function sendCompletionNotification($userId, $orderTitle) {
        try {
            $user = $this->db->fetchOne("SELECT phone FROM users WHERE id = ?", [$userId]);
            if ($user) {
                // 这里可以发送自定义的完成通知短信
                // 为了简化，暂时记录日志
                error_log("分析完成通知 - 用户: {$user['phone']}, 订单: {$orderTitle}");
            }
        } catch (Exception $e) {
            error_log("发送完成通知失败: " . $e->getMessage());
        }
    }
    
    /**
     * 删除订单
     */
    public function deleteOrder($orderId) {
        $order = $this->getOrderById($orderId);
        if (!$order) {
            throw new Exception('订单不存在');
        }
        
        $this->db->beginTransaction();
        
        try {
            // 如果订单未完成，退还精灵币
            if ($order['status'] === 'pending' || $order['status'] === 'processing') {
                $user = new User();
                $user->rechargeCoins(
                    $order['user_id'], 
                    $order['cost_coins'], 
                    null, 
                    "订单删除退款：{$order['title']}"
                );
            }
            
            // 删除相关文件
            $this->deleteOrderFiles($order);
            
            // 删除订单
            $this->db->query("DELETE FROM analysis_orders WHERE id = ?", [$orderId]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 删除订单相关文件
     */
    private function deleteOrderFiles($order) {
        // 删除截图文件
        if ($order['live_screenshots']) {
            $screenshots = json_decode($order['live_screenshots'], true);
            foreach ($screenshots as $screenshot) {
                $filePath = BASE_PATH . $screenshot;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
        
        // 删除封面图
        if ($order['cover_image']) {
            $filePath = BASE_PATH . $order['cover_image'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
    
    /**
     * 删除订单
     */
    public function deleteOrder($orderId) {
        $this->db->beginTransaction();
        try {
            // 获取订单信息
            $order = $this->getOrderById($orderId);
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            // 如果订单已完成，需要退还精灵币
            if ($order['status'] == 'completed') {
                // 退还精灵币
                $this->db->query(
                    "UPDATE users SET jingling_coins = jingling_coins + ? WHERE id = ?",
                    [$order['cost_coins'], $order['user_id']]
                );
                
                // 记录退款交易
                $this->db->insert(
                    "INSERT INTO coin_transactions (user_id, type, amount, balance_after, related_order_id, description) 
                     SELECT ?, 'refund', ?, jingling_coins, ?, '管理员删除订单退款' FROM users WHERE id = ?",
                    [$order['user_id'], $order['cost_coins'], $orderId, $order['user_id']]
                );
            }
            
            // 删除相关文件
            $this->deleteOrderFiles($order);
            
            // 删除订单记录
            $this->db->query("DELETE FROM analysis_orders WHERE id = ?", [$orderId]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
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