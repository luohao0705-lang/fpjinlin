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
            
            // 创建订单 - 初始状态为reviewing，等待管理员配置FLV地址
            $orderId = $this->db->insert(
                "INSERT INTO video_analysis_orders (user_id, order_no, title, self_video_link, competitor_video_links, cost_coins, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'reviewing', NOW())",
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
            $userObj->deductCoins($userId, $costCoins, '视频分析订单消费', 'video_analysis', null, false);
            
            $this->db->commit();
            
            return [
                'orderId' => $orderId,
                'orderNo' => $orderNo,
                'costCoins' => $costCoins
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("创建视频分析订单异常: " . $e->getMessage());
            error_log("错误堆栈: " . $e->getTraceAsString());
            throw new Exception("数据库操作失败: " . $e->getMessage(), 0, $e);
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
                "INSERT INTO video_files (order_id, video_type, video_index, original_url, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                [$orderId, 'competitor', $index + 1, $link]
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
        // 获取视频文件列表，为每个视频文件创建录制任务
        $videoFiles = $this->db->fetchAll(
            "SELECT id FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
            [$orderId]
        );
        
        $tasks = [
            ['type' => 'record', 'priority' => 10],
            ['type' => 'transcode', 'priority' => 9],
            ['type' => 'segment', 'priority' => 8],
            ['type' => 'asr', 'priority' => 7],
            ['type' => 'analysis', 'priority' => 6],
            ['type' => 'report', 'priority' => 5]
        ];
        
        foreach ($tasks as $task) {
            if ($task['type'] === 'record') {
                // 为每个视频文件创建录制任务
                foreach ($videoFiles as $videoFile) {
                    $this->db->insert(
                        "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                        [$orderId, $task['type'], json_encode(['video_file_id' => $videoFile['id']]), $task['priority']]
                    );
                }
            } else {
                // 其他任务只创建一次
                $this->db->insert(
                    "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                    [$orderId, $task['type'], json_encode([]), $task['priority']]
                );
            }
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
    
    /**
     * 启动视频分析
     */
    public function startAnalysis($orderId) {
        $this->db->beginTransaction();
        
        try {
            // 检查订单状态
            $order = $this->getOrderById($orderId);
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            if (!in_array($order['status'], ['reviewing', 'processing', 'failed'])) {
                throw new Exception('订单状态不允许启动分析');
            }
            
            // 检查是否已填写FLV地址
            $videoFiles = $this->db->fetchAll(
                "SELECT * FROM video_files WHERE order_id = ? AND (flv_url IS NULL OR flv_url = '')",
                [$orderId]
            );
            
            if (!empty($videoFiles)) {
                throw new Exception('请先填写所有视频的FLV地址');
            }
            
            // 如果状态是reviewing，说明管理员刚配置完FLV地址，可以开始分析
            if ($order['status'] === 'reviewing') {
                // 更新订单状态为处理中
                $this->updateOrderStatus($orderId, 'processing');
            }
            
            // 创建处理任务
            $this->createProcessingTasks($orderId);
            
            // 立即开始处理第一个任务（录制任务）
            $this->startProcessingTasks($orderId);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '分析已启动，正在自动处理中...'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 停止视频分析
     */
    public function stopAnalysis($orderId) {
        $this->db->beginTransaction();
        
        try {
            // 更新订单状态为失败
            $this->updateOrderStatus($orderId, 'failed', null, null, null, '管理员手动停止');
            
            // 停止所有处理中的任务
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'failed', error_message = '管理员手动停止' 
                 WHERE order_id = ? AND status IN ('pending', 'processing')",
                [$orderId]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '分析已停止'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 创建处理任务
     */
    private function createProcessingTasks($orderId) {
        // 检查是否已有处理任务
        $existingTasks = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM video_processing_queue WHERE order_id = ?",
            [$orderId]
        )['count'];
        
        if ($existingTasks > 0) {
            // 如果已有任务，重置失败的任务为待处理
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'pending', error_message = NULL 
                 WHERE order_id = ? AND status = 'failed'",
                [$orderId]
            );
            return;
        }
        
        $videoFiles = $this->db->fetchAll(
            "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
            [$orderId]
        );
        
        // 为每个视频文件创建处理任务
        foreach ($videoFiles as $videoFile) {
            // 1. 录制任务 - 从FLV流录制视频
            $this->db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'record', ?, 1, 'pending')",
                [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
            );
            
            // 2. 转码任务 - 转码为统一格式
            $this->db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'transcode', ?, 2, 'pending')",
                [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
            );
            
            // 3. 切片任务 - 按配置时长切片
            $this->db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'segment', ?, 3, 'pending')",
                [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
            );
            
            // 4. 语音识别任务 - 对每个切片进行ASR
            $this->db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'asr', ?, 4, 'pending')",
                [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
            );
        }
        
        // 视频理解任务
        $this->db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'analysis', ?, 5, 'pending')",
            [$orderId, json_encode(['order_id' => $orderId])]
        );
        
        // 报告生成任务
        $this->db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'report', ?, 6, 'pending')",
            [$orderId, json_encode(['order_id' => $orderId])]
        );
    }
    
    /**
     * 开始处理任务
     */
    private function startProcessingTasks($orderId) {
        // 获取第一个待处理任务
        $firstTask = $this->db->fetchOne(
            "SELECT * FROM video_processing_queue 
             WHERE order_id = ? AND status = 'pending' 
             ORDER BY priority DESC, created_at ASC 
             LIMIT 1",
            [$orderId]
        );
        
        if ($firstTask) {
            // 立即处理第一个任务
            $this->processTask($firstTask);
        }
    }
    
    /**
     * 处理单个任务
     */
    private function processTask($task) {
        try {
            // 更新任务状态为处理中
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'processing', started_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            $taskData = json_decode($task['task_data'], true);
            
            // 根据任务类型进行处理
            switch ($task['task_type']) {
                case 'record':
                    $this->processRecordTask($taskData);
                    break;
                case 'transcode':
                    $this->processTranscodeTask($taskData);
                    break;
                case 'segment':
                    $this->processSegmentTask($taskData);
                    break;
                case 'asr':
                    $this->processAsrTask($taskData);
                    break;
                case 'analysis':
                    $this->processAnalysisTask($task['order_id']);
                    break;
                case 'report':
                    $this->processReportTask($task['order_id']);
                    break;
            }
            
            // 更新任务状态为完成
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'completed', completed_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            // 处理下一个任务
            $this->processNextTask($task['order_id']);
            
        } catch (Exception $e) {
            // 更新任务状态为失败
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'failed', error_message = ? WHERE id = ?",
                [$e->getMessage(), $task['id']]
            );
            error_log("任务处理失败: {$task['task_type']} - " . $e->getMessage());
        }
    }
    
    /**
     * 处理下一个任务
     */
    private function processNextTask($orderId) {
        $nextTask = $this->db->fetchOne(
            "SELECT * FROM video_processing_queue 
             WHERE order_id = ? AND status = 'pending' 
             ORDER BY priority DESC, created_at ASC 
             LIMIT 1",
            [$orderId]
        );
        
        if ($nextTask) {
            $this->processTask($nextTask);
        } else {
            // 所有任务完成，更新订单状态
            $this->updateOrderStatus($orderId, 'completed');
        }
    }
    
    /**
     * 处理录制任务
     */
    private function processRecordTask($taskData) {
        $videoFileId = $taskData['video_file_id'];
        $videoFile = $this->db->fetchOne("SELECT * FROM video_files WHERE id = ?", [$videoFileId]);
        
        if (!$videoFile || empty($videoFile['flv_url'])) {
            throw new Exception('视频文件或FLV地址不存在');
        }
        
        require_once __DIR__ . '/VideoProcessor.php';
        $videoProcessor = new VideoProcessor();
        $videoProcessor->recordVideo($videoFileId, $videoFile['flv_url']);
    }
    
    /**
     * 处理转码任务
     */
    private function processTranscodeTask($taskData) {
        require_once __DIR__ . '/VideoProcessor.php';
        $videoProcessor = new VideoProcessor();
        $videoProcessor->transcodeVideo($taskData['video_file_id']);
    }
    
    /**
     * 处理切片任务
     */
    private function processSegmentTask($taskData) {
        require_once __DIR__ . '/VideoProcessor.php';
        $videoProcessor = new VideoProcessor();
        $videoProcessor->segmentVideo($taskData['video_file_id']);
    }
    
    /**
     * 处理ASR任务
     */
    private function processAsrTask($taskData) {
        require_once __DIR__ . '/WhisperService.php';
        $whisperService = new WhisperService();
        
        $segments = $this->db->fetchAll(
            "SELECT vs.* FROM video_segments vs 
             WHERE vs.video_file_id = ? AND vs.status = 'completed'",
            [$taskData['video_file_id']]
        );
        
        foreach ($segments as $segment) {
            $whisperService->processSegment($segment['id']);
        }
    }
    
    /**
     * 处理分析任务
     */
    private function processAnalysisTask($orderId) {
        require_once __DIR__ . '/QwenOmniService.php';
        $qwenOmniService = new QwenOmniService();
        
        $segments = $this->db->fetchAll(
            "SELECT vs.* FROM video_segments vs 
             LEFT JOIN video_files vf ON vs.video_file_id = vf.id 
             WHERE vf.order_id = ? AND vs.status = 'completed'",
            [$orderId]
        );
        
        foreach ($segments as $segment) {
            $qwenOmniService->analyzeSegment($segment['id']);
        }
    }
    
    /**
     * 处理报告任务
     */
    private function processReportTask($orderId) {
        require_once __DIR__ . '/VideoAnalysisEngine.php';
        $videoAnalysisEngine = new VideoAnalysisEngine();
        $videoAnalysisEngine->processVideoAnalysis($orderId);
    }
}
