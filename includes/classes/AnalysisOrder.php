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
            try {
                $costCoins = getSystemConfig('analysis_cost_coins', DEFAULT_ANALYSIS_COST);
            } catch (Exception $configError) {
                error_log("获取系统配置失败: " . $configError->getMessage());
                $costCoins = DEFAULT_ANALYSIS_COST; // 使用默认值
            }
            
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
            error_log("创建分析订单异常: " . $e->getMessage());
            error_log("用户ID: {$userId}, 标题: {$title}");
            error_log("错误堆栈: " . $e->getTraceAsString());
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
            error_log("尝试启动后台分析处理：订单ID {$orderId}");
            
            // 检查exec函数是否可用
            $execAvailable = function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')));
            error_log("exec函数可用性: " . ($execAvailable ? '是' : '否'));
            
            if ($execAvailable) {
                $scriptPath = dirname(__DIR__, 2) . '/scripts/process_analysis.php';
                error_log("脚本路径: {$scriptPath}");
                
                if (file_exists($scriptPath)) {
                    $command = "php {$scriptPath} {$orderId} > /dev/null 2>&1 &";
                    exec($command);
                    error_log("启动后台分析处理命令：{$command}");
                } else {
                    error_log("分析脚本文件不存在: {$scriptPath}");
                    // 暂时不执行分析，只创建订单
                }
            } else {
                error_log("exec函数不可用，暂时不执行后台分析");
                // 暂时不执行分析，只创建订单
            }
        } catch (Exception $e) {
            error_log("启动分析处理失败：订单ID {$orderId} - " . $e->getMessage());
            error_log("启动分析错误堆栈: " . $e->getTraceAsString());
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