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
            
            // 扣除精灵币
            $userObj->deductCoins($userId, $costCoins, '分析订单消费', 'analysis_order', $orderId);
            
            $this->db->commit();
            
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
            $analysisResult = $deepSeekService->analyzeScript(
                $order['title'],
                $order['own_script'],
                [
                    $order['competitor1_script'],
                    $order['competitor2_script'],
                    $order['competitor3_script']
                ]
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
}