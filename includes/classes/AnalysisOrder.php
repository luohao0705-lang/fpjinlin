<?php
/**
 * 分析订单管理类
 * 复盘精灵主系统版本
 */

class AnalysisOrder {
    private $db;
    
    public function __construct() {
        // 优先使用单例模式，如果不可用则创建新实例
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
    }
    
    /**
     * 创建分析订单
     */
    public function createOrder($userId, $title, $ownScript, $competitorScripts = []) {
        $this->db->beginTransaction();
        
        try {
            // 检查精灵币余额
            $costCoins = getSystemConfig('analysis_cost_coins', 100);
            $userObj = new User();
            if (!$userObj->checkCoinsBalance($userId, $costCoins)) {
                throw new Exception('精灵币余额不足，请先充值');
            }
            
            // 生成订单号
            $orderNo = generateOrderNo();
            
            // 创建订单
            $orderId = $this->db->insert(
                "INSERT INTO analysis_orders (user_id, order_no, title, self_script, competitor_scripts, cost_coins, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [
                    $userId, 
                    $orderNo, 
                    $title, 
                    $ownScript,
                    json_encode($competitorScripts, JSON_UNESCAPED_UNICODE),
                    $costCoins
                ]
            );
            
            // 扣除精灵币（在当前事务中执行）
            $userObj->deductCoins($userId, $costCoins, '分析订单消费', 'analysis_order', $orderId, false);
            
            $this->db->commit();
            
            // 启动后台分析处理
            $this->startBackgroundAnalysis($orderId);
            
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
     * 获取订单详情
     */
    public function getOrderById($orderId) {
        return $this->db->fetchOne(
            "SELECT ao.*, u.phone as user_phone FROM analysis_orders ao 
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
            "SELECT * FROM analysis_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
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
     * 更新订单状态
     */
    public function updateOrderStatus($orderId, $status, $result = null) {
        $params = [$status, $orderId];
        $sql = "UPDATE analysis_orders SET status = ?";
        
        if ($result !== null) {
            $sql .= ", analysis_result = ?";
            $params = [$status, $result, $orderId];
        }
        
        $sql .= " WHERE id = ?";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * 删除订单
     */
    public function deleteOrder($orderId) {
        return $this->db->query("DELETE FROM analysis_orders WHERE id = ?", [$orderId]);
    }
    
    /**
     * 获取统计数据
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // 总订单数
            $total = $this->db->fetchOne("SELECT COUNT(*) as count FROM analysis_orders");
            $stats['total'] = $total ? $total['count'] : 0;
            
            // 各状态订单数
            $statusStats = $this->db->fetchAll(
                "SELECT status, COUNT(*) as count FROM analysis_orders GROUP BY status"
            );
            
            $stats['pending'] = 0;
            $stats['processing'] = 0;
            $stats['completed'] = 0;
            $stats['failed'] = 0;
            
            foreach ($statusStats as $stat) {
                $stats[$stat['status']] = $stat['count'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("获取订单统计失败: " . $e->getMessage());
            return [
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0
            ];
        }
    }
    
    /**
     * 处理分析请求
     */
    public function processAnalysis($orderId) {
        $order = $this->getOrderById($orderId);
        if (!$order) {
            throw new Exception('订单不存在');
        }
        
        if ($order['status'] !== 'pending') {
            throw new Exception('订单状态不允许处理');
        }
        
        try {
            // 更新状态为处理中
            $this->updateOrderStatus($orderId, 'processing');
            
            // 调用AI分析服务
            $deepSeekService = new DeepSeekService();
            $competitorScripts = json_decode($order['competitor_scripts'], true) ?: [];
            $analysisResult = $deepSeekService->generateAnalysisReport(
                [], // screenshots - 暂时为空
                '', // coverImage - 暂时为空
                $order['self_script'],
                $competitorScripts
            );
            
            // 更新订单结果
            $this->updateOrderStatus($orderId, 'completed', json_encode($analysisResult, JSON_UNESCAPED_UNICODE));
            
            return $analysisResult;
            
        } catch (Exception $e) {
            // 更新状态为失败
            $this->updateOrderStatus($orderId, 'failed');
            throw $e;
        }
    }
    
    /**
     * 启动后台分析处理
     */
    private function startBackgroundAnalysis($orderId) {
        try {
            // 尝试使用异步方式启动分析
            if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                $scriptPath = dirname(__DIR__, 2) . '/scripts/process_analysis.php';
                $command = "php {$scriptPath} {$orderId} > /dev/null 2>&1 &";
                exec($command);
                error_log("启动后台分析处理：订单ID {$orderId}");
            } else {
                // 如果不能异步执行，立即处理
                error_log("无法异步执行，立即处理分析：订单ID {$orderId}");
                $this->processAnalysis($orderId);
            }
        } catch (Exception $e) {
            error_log("启动分析处理失败：订单ID {$orderId} - " . $e->getMessage());
        }
    }
    
    /**
     * 更新订单状态
     */
    public function updateOrderStatus($orderId, $status, $report = null, $score = null, $level = null, $errorMessage = null) {
        $updateData = ['status' => $status];
        $params = [$status];
        $setClause = 'status = ?';
        
        if ($status === 'completed') {
            $setClause .= ', completed_at = NOW()';
            if ($report) {
                $setClause .= ', ai_report = ?';
                $params[] = $report;
            }
            if ($score !== null) {
                $setClause .= ', report_score = ?';
                $params[] = $score;
            }
            if ($level) {
                $setClause .= ', report_level = ?';
                $params[] = $level;
            }
        } elseif ($status === 'failed' && $errorMessage) {
            $setClause .= ', error_message = ?';
            $params[] = $errorMessage;
        }
        
        $params[] = $orderId;
        
        $this->db->query(
            "UPDATE analysis_orders SET {$setClause} WHERE id = ?",
            $params
        );
    }
}