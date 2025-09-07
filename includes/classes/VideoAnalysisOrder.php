<?php
/**
 * 视频分析订单管理类
 * 复盘精灵系统 - 视频驱动分析
 */

class VideoAnalysisOrder {
    private $db;
    
    public function __construct() {
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
    }
    
    /**
     * 创建视频分析订单
     */
    public function createOrder($userId, $title, $selfVideoLink, $competitorVideoLinks = []) {
        $this->db->beginTransaction();
        
        try {
            // 检查精灵币余额
            $costCoins = getSystemConfig('video_analysis_cost_coins', 50);
            $userObj = new User();
            if (!$userObj->checkCoinsBalance($userId, $costCoins)) {
                throw new Exception('精灵币余额不足，请先充值');
            }
            
            // 生成订单号
            $orderNo = 'VA' . date('YmdHis') . rand(1000, 9999);
            
            // 创建订单
            $orderId = $this->db->insert(
                "INSERT INTO video_analysis_orders (user_id, order_no, title, self_video_link, competitor_video_links, cost_coins, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [
                    $userId, 
                    $orderNo, 
                    $title, 
                    $selfVideoLink,
                    json_encode($competitorVideoLinks, JSON_UNESCAPED_UNICODE),
                    $costCoins
                ]
            );
            
            // 创建视频文件记录
            $this->createVideoFileRecords($orderId, $selfVideoLink, $competitorVideoLinks);
            
            // 扣除精灵币
            $userObj->deductCoins($userId, $costCoins, '视频分析订单消费', 'video_analysis', $orderId, false);
            
            $this->db->commit();
            
            return [
                'orderId' => $orderId,
                'orderNo' => $orderNo,
                'costCoins' => $costCoins
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("创建视频分析订单异常: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 创建视频文件记录
     */
    private function createVideoFileRecords($orderId, $selfVideoLink, $competitorVideoLinks) {
        // 本方视频
        $this->db->insert(
            "INSERT INTO video_files (order_id, video_type, video_index, original_url, status, created_at) VALUES (?, 'self', 0, ?, 'pending', NOW())",
            [$orderId, $selfVideoLink]
        );
        
        // 同行视频
        foreach ($competitorVideoLinks as $index => $link) {
            $this->db->insert(
                "INSERT INTO video_files (order_id, video_type, video_index, original_url, status, created_at) VALUES (?, 'competitor', ?, ?, 'pending', NOW())",
                [$orderId, $index + 1, $link]
            );
        }
    }
    
    /**
     * 审核通过订单
     */
    public function approveOrder($orderId, $selfFlvUrl, $competitorFlvUrls = []) {
        $this->db->beginTransaction();
        
        try {
            // 更新订单状态
            $this->db->query(
                "UPDATE video_analysis_orders SET status = 'reviewing', self_flv_url = ?, competitor_flv_urls = ?, reviewed_at = NOW() WHERE id = ?",
                [$selfFlvUrl, json_encode($competitorFlvUrls, JSON_UNESCAPED_UNICODE), $orderId]
            );
            
            // 更新视频文件FLV地址
            $this->updateVideoFileFlvUrls($orderId, $selfFlvUrl, $competitorFlvUrls);
            
            // 添加到处理队列
            $this->addToProcessingQueue($orderId);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 更新视频文件FLV地址
     */
    private function updateVideoFileFlvUrls($orderId, $selfFlvUrl, $competitorFlvUrls) {
        // 更新本方视频
        $this->db->query(
            "UPDATE video_files SET flv_url = ? WHERE order_id = ? AND video_type = 'self'",
            [$selfFlvUrl, $orderId]
        );
        
        // 更新同行视频
        foreach ($competitorFlvUrls as $index => $flvUrl) {
            $this->db->query(
                "UPDATE video_files SET flv_url = ? WHERE order_id = ? AND video_type = 'competitor' AND video_index = ?",
                [$flvUrl, $orderId, $index + 1]
            );
        }
    }
    
    /**
     * 添加到处理队列
     */
    private function addToProcessingQueue($orderId) {
        $tasks = [
            ['type' => 'download', 'priority' => 10],
            ['type' => 'transcode', 'priority' => 9],
            ['type' => 'segment', 'priority' => 8],
            ['type' => 'asr', 'priority' => 7],
            ['type' => 'analysis', 'priority' => 6],
            ['type' => 'report', 'priority' => 5]
        ];
        
        foreach ($tasks as $task) {
            $this->db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                [$orderId, $task['type'], json_encode([]), $task['priority']]
            );
        }
    }
    
    /**
     * 获取订单详情
     */
    public function getOrderById($orderId) {
        return $this->db->fetchOne(
            "SELECT vao.*, u.phone as user_phone FROM video_analysis_orders vao 
             LEFT JOIN users u ON vao.user_id = u.id 
             WHERE vao.id = ?",
            [$orderId]
        );
    }
    
    /**
     * 获取用户订单列表
     */
    public function getUserOrders($userId, $page = 1, $pageSize = 20) {
        $offset = ($page - 1) * $pageSize;
        
        $orders = $this->db->fetchAll(
            "SELECT * FROM video_analysis_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$userId, $pageSize, $offset]
        );
        
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM video_analysis_orders WHERE user_id = ?",
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
     * 获取待审核订单列表
     */
    public function getPendingReviewOrders($page = 1, $pageSize = 20) {
        $offset = ($page - 1) * $pageSize;
        
        $orders = $this->db->fetchAll(
            "SELECT vao.*, u.phone as user_phone FROM video_analysis_orders vao 
             LEFT JOIN users u ON vao.user_id = u.id 
             WHERE vao.status = 'pending' 
             ORDER BY vao.created_at ASC LIMIT ? OFFSET ?",
            [$pageSize, $offset]
        );
        
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM video_analysis_orders WHERE status = 'pending'"
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
    public function updateOrderStatus($orderId, $status, $report = null, $score = null, $level = null, $errorMessage = null) {
        $updateData = ['status' => $status];
        $params = [$status];
        $setClause = 'status = ?';
        
        if ($status === 'processing') {
            $setClause .= ', processing_started_at = NOW()';
        } elseif ($status === 'completed') {
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
            "UPDATE video_analysis_orders SET {$setClause} WHERE id = ?",
            $params
        );
    }
    
    /**
     * 获取统计数据
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // 总订单数
            $total = $this->db->fetchOne("SELECT COUNT(*) as count FROM video_analysis_orders");
            $stats['total'] = $total ? $total['count'] : 0;
            
            // 各状态订单数
            $statusStats = $this->db->fetchAll(
                "SELECT status, COUNT(*) as count FROM video_analysis_orders GROUP BY status"
            );
            
            $stats['pending'] = 0;
            $stats['reviewing'] = 0;
            $stats['processing'] = 0;
            $stats['completed'] = 0;
            $stats['failed'] = 0;
            
            foreach ($statusStats as $stat) {
                $stats[$stat['status']] = $stat['count'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("获取视频分析订单统计失败: " . $e->getMessage());
            return [
                'total' => 0,
                'pending' => 0,
                'reviewing' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0
            ];
        }
    }
}
