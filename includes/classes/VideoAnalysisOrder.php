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
            
            if (!in_array($order['status'], ['reviewing', 'processing', 'failed', 'stopped'])) {
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
            
            // 如果状态是reviewing或stopped，可以开始分析
            if (in_array($order['status'], ['reviewing', 'stopped'])) {
                // 更新订单状态为处理中
                $this->updateOrderStatus($orderId, 'processing');
            }
            
            // 创建处理任务
            $this->createProcessingTasks($orderId);
            
            $this->db->commit();
            
            // 在事务外启动任务处理
            $this->startProcessingTasks($orderId);
            
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
            // 更新订单状态为已停止（可以重新启动）
            $this->updateOrderStatus($orderId, 'stopped', null, null, null, '管理员手动停止');
            
            // 停止所有处理中的任务，但保持为pending状态以便重新启动
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'pending', error_message = '管理员手动停止，可重新启动' 
                 WHERE order_id = ? AND status IN ('pending', 'processing')",
                [$orderId]
            );
            
            // 终止正在运行的FFmpeg进程
            $this->terminateFFmpegProcesses($orderId);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '分析已停止，可以重新启动'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 终止FFmpeg进程
     */
    private function terminateFFmpegProcesses($orderId) {
        try {
            // 查找相关的FFmpeg进程
            $output = [];
            exec("ps aux | grep ffmpeg | grep -v grep", $output);
            
            foreach ($output as $line) {
                if (strpos($line, "video_") !== false) {
                    // 提取进程ID
                    preg_match('/\s+(\d+)\s+/', $line, $matches);
                    if (isset($matches[1])) {
                        $pid = $matches[1];
                        exec("kill -TERM {$pid} 2>/dev/null");
                        error_log("终止FFmpeg进程: {$pid}");
                    }
                }
            }
        } catch (Exception $e) {
            error_log("终止FFmpeg进程失败: " . $e->getMessage());
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
            // 如果已有任务，重置失败和停止的任务为待处理
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'pending', error_message = NULL, retry_count = 0
                 WHERE order_id = ? AND status IN ('failed', 'stopped')",
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
            // 1. 录制任务 - 最高优先级 (10)
            $this->db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'record', ?, 10, 'pending')",
                [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
            );
            
            // 2. 转码任务 (8)
            $this->db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'transcode', ?, 8, 'pending')",
                [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
            );
            
            // 3. 切片任务 (6)
            $this->db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'segment', ?, 6, 'pending')",
                [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
            );
            
            // 4. 语音识别任务 (4)
            $this->db->insert(
                "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'asr', ?, 4, 'pending')",
                [$orderId, json_encode(['video_file_id' => $videoFile['id']])]
            );
        }
        
        // 5. AI分析任务 (2)
        $this->db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'analysis', ?, 2, 'pending')",
            [$orderId, json_encode(['order_id' => $orderId])]
        );
        
        // 6. 报告生成任务 - 最低优先级 (1)
        $this->db->insert(
            "INSERT INTO video_processing_queue (order_id, task_type, task_data, priority, status) VALUES (?, 'report', ?, 1, 'pending')",
            [$orderId, json_encode(['order_id' => $orderId])]
        );
    }
    
    /**
     * 开始处理任务
     */
    private function startProcessingTasks($orderId) {
        // 从系统配置获取并发数量
        $maxConcurrent = $this->getSystemConfig('max_concurrent_processing', 2);
        
        // 获取待处理的录制任务
        $recordTasks = $this->db->fetchAll(
            "SELECT * FROM video_processing_queue 
             WHERE order_id = ? AND status = 'pending' AND task_type = 'record'
             ORDER BY priority DESC, created_at ASC
             LIMIT ?",
            [$orderId, $maxConcurrent]
        );
        
        if (!empty($recordTasks)) {
            // 分批处理录制任务
            foreach ($recordTasks as $task) {
                $this->processTaskWithRetry($task);
            }
        }
    }
    
    /**
     * 带重试机制的任务处理
     */
    private function processTaskWithRetry($task) {
        $maxRetries = 3;
        $retryCount = 0;
        
        while ($retryCount < $maxRetries) {
            try {
                // 更新任务状态为处理中
                $this->db->query(
                    "UPDATE video_processing_queue SET status = 'processing', started_at = NOW(), retry_count = ? WHERE id = ?",
                    [$retryCount, $task['id']]
                );
                
                // 执行任务
                $this->executeTaskWithDiagnostics($task);
                
                // 任务成功，跳出重试循环
                break;
                
            } catch (Exception $e) {
                $retryCount++;
                $errorMsg = $e->getMessage();
                
                // 记录详细错误信息
                $this->logTaskError($task, $errorMsg, $retryCount, $maxRetries);
                
                if ($retryCount >= $maxRetries) {
                    // 达到最大重试次数，标记为失败
                    $this->db->query(
                        "UPDATE video_processing_queue SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?",
                        ["重试{$maxRetries}次后仍然失败: {$errorMsg}", $task['id']]
                    );
                    break;
                } else {
                    // 等待一段时间后重试
                    sleep(pow(2, $retryCount)); // 指数退避：2, 4, 8秒
                }
            }
        }
    }
    
    /**
     * 记录任务错误详情
     */
    private function logTaskError($task, $errorMsg, $retryCount, $maxRetries) {
        $diagnostics = [
            'task_id' => $task['id'],
            'task_type' => $task['task_type'],
            'error' => $errorMsg,
            'retry_count' => $retryCount,
            'max_retries' => $maxRetries,
            'timestamp' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true),
            'cpu_load' => sys_getloadavg()[0] ?? 'unknown'
        ];
        
        error_log("❌ 任务错误诊断: " . json_encode($diagnostics, JSON_UNESCAPED_UNICODE));
        
        // 更新任务错误信息
        $this->db->query(
            "UPDATE video_processing_queue SET error_message = ? WHERE id = ?",
            ["重试{$retryCount}/{$maxRetries}: {$errorMsg}", $task['id']]
        );
    }
    
    /**
     * 异步处理任务（用于并发录制）
     */
    private function processTaskAsync($task) {
        // 更新任务状态为处理中
        $this->db->query(
            "UPDATE video_processing_queue SET status = 'processing', started_at = NOW() WHERE id = ?",
            [$task['id']]
        );
        
        // 在后台执行任务
        $this->executeTaskInBackground($task);
    }
    
    /**
     * 带诊断的任务执行
     */
    private function executeTaskWithDiagnostics($task) {
        $taskData = json_decode($task['task_data'], true);
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        try {
            // 记录任务开始信息
            error_log("🚀 开始执行任务: {$task['task_type']} (ID: {$task['id']})");
            
            // 根据任务类型进行处理
            switch ($task['task_type']) {
                case 'record':
                    $this->processRecordTaskWithDiagnostics($taskData, $task['id']);
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
                    $this->processAnalysisTask($taskData);
                    break;
                case 'report':
                    $this->processReportTask($taskData);
                    break;
                default:
                    throw new Exception('未知任务类型: ' . $task['task_type']);
            }
            
            // 记录任务完成信息
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            $duration = round($endTime - $startTime, 2);
            $memoryUsed = $endMemory - $startMemory;
            
            error_log("✅ 任务完成: {$task['task_type']} (ID: {$task['id']}) - 耗时: {$duration}s, 内存: " . $this->formatBytes($memoryUsed));
            
            // 更新任务状态为完成
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'completed', completed_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            // 如果是录制任务完成，检查是否所有录制都完成了
            if ($task['task_type'] === 'record') {
                $this->checkAllRecordsCompleted($task['order_id']);
            }
            
        } catch (Exception $e) {
            // 记录任务失败信息
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            error_log("❌ 任务失败: {$task['task_type']} (ID: {$task['id']}) - 耗时: {$duration}s, 错误: " . $e->getMessage());
            
            // 更新任务状态为失败
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'failed', error_message = ? WHERE id = ?",
                [$e->getMessage(), $task['id']]
            );
            
            throw $e; // 重新抛出异常，让重试机制处理
        }
    }
    
    /**
     * 带诊断的录制任务处理
     */
    private function processRecordTaskWithDiagnostics($taskData, $taskId) {
        $videoFileId = $taskData['video_file_id'];
        $videoFile = $this->db->fetchOne("SELECT * FROM video_files WHERE id = ?", [$videoFileId]);
        
        if (!$videoFile || empty($videoFile['flv_url'])) {
            throw new Exception('视频文件或FLV地址不存在');
        }
        
        // 检查FLV地址是否有效
        $this->validateFlvUrl($videoFile['flv_url']);
        
        // 检查系统资源
        $this->checkSystemResources();
        
        $videoProcessor = new VideoProcessor();
        $videoProcessor->recordVideo($videoFileId, $videoFile['flv_url']);
    }
    
    /**
     * 验证FLV地址
     */
    private function validateFlvUrl($flvUrl) {
        // 检查URL格式
        if (!filter_var($flvUrl, FILTER_VALIDATE_URL)) {
            throw new Exception('FLV地址格式无效');
        }
        
        // 检查是否是抖音FLV地址
        if (strpos($flvUrl, 'douyincdn.com') === false) {
            error_log("⚠️ 非抖音FLV地址: {$flvUrl}");
        }
        
        // 检查地址是否过期（通过expire参数）
        if (preg_match('/expire=(\d+)/', $flvUrl, $matches)) {
            $expireTime = intval($matches[1]);
            $currentTime = time();
            
            if ($expireTime < $currentTime) {
                throw new Exception('FLV地址已过期，请重新获取');
            }
            
            $remainingTime = $expireTime - $currentTime;
            if ($remainingTime < 300) { // 少于5分钟
                error_log("⚠️ FLV地址即将过期: {$remainingTime}秒后过期");
            }
        }
    }
    
    /**
     * 检查系统资源
     */
    private function checkSystemResources() {
        // 检查内存使用率
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        
        if ($memoryUsage > $memoryLimitBytes * 0.8) {
            throw new Exception('内存使用率过高，请稍后重试');
        }
        
        // 检查CPU负载
        $loadAvg = sys_getloadavg();
        if ($loadAvg[0] > 4.0) { // 1分钟平均负载
            throw new Exception('CPU负载过高，请稍后重试');
        }
        
        // 检查磁盘空间
        $freeSpace = disk_free_space(sys_get_temp_dir());
        if ($freeSpace < 1024 * 1024 * 1024) { // 少于1GB
            throw new Exception('磁盘空间不足，请清理临时文件');
        }
    }
    
    /**
     * 解析内存限制
     */
    private function parseMemoryLimit($memoryLimit) {
        $unit = strtolower(substr($memoryLimit, -1));
        $value = intval($memoryLimit);
        
        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
    
    /**
     * 获取系统配置
     */
    private function getSystemConfig($key, $defaultValue = null) {
        try {
            $config = $this->db->fetchOne(
                "SELECT config_value FROM system_config WHERE config_key = ?",
                [$key]
            );
            
            if ($config && isset($config['config_value'])) {
                return $config['config_value'];
            }
            
            return $defaultValue;
        } catch (Exception $e) {
            error_log("获取系统配置失败: {$key} - " . $e->getMessage());
            return $defaultValue;
        }
    }
    
    /**
     * 格式化字节数
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * 在后台执行任务
     */
    private function executeTaskInBackground($task) {
        $taskData = json_decode($task['task_data'], true);
        
        try {
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
                    $this->processAnalysisTask($taskData);
                    break;
                case 'report':
                    $this->processReportTask($taskData);
                    break;
                default:
                    throw new Exception('未知任务类型: ' . $task['task_type']);
            }
            
            // 更新任务状态为完成
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'completed', completed_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            // 如果是录制任务完成，检查是否所有录制都完成了
            if ($task['task_type'] === 'record') {
                $this->checkAllRecordsCompleted($task['order_id']);
            }
            
        } catch (Exception $e) {
            // 更新任务状态为失败
            $this->db->query(
                "UPDATE video_processing_queue SET status = 'failed', error_message = ? WHERE id = ?",
                [$e->getMessage(), $task['id']]
            );
            error_log("异步任务处理失败: {$task['task_type']} - " . $e->getMessage());
        }
    }
    
    /**
     * 检查所有录制是否完成，如果完成则开始后续任务
     */
    private function checkAllRecordsCompleted($orderId) {
        // 检查是否还有未完成的录制任务
        $pendingRecords = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM video_processing_queue 
             WHERE order_id = ? AND task_type = 'record' AND status IN ('pending', 'processing')",
            [$orderId]
        )['count'];
        
        if ($pendingRecords == 0) {
            // 所有录制完成，开始处理后续任务
            $this->startNextPhaseTasks($orderId);
        }
    }
    
    /**
     * 开始下一阶段的任务（转码、切片等）
     */
    private function startNextPhaseTasks($orderId) {
        // 获取下一个待处理任务
        $nextTask = $this->db->fetchOne(
            "SELECT * FROM video_processing_queue 
             WHERE order_id = ? AND status = 'pending' 
             ORDER BY priority DESC, created_at ASC 
             LIMIT 1",
            [$orderId]
        );
        
        if ($nextTask) {
            $this->processTask($nextTask);
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
        
        $videoProcessor = new VideoProcessor();
        $videoProcessor->recordVideo($videoFileId, $videoFile['flv_url']);
    }
    
    /**
     * 处理转码任务
     */
    private function processTranscodeTask($taskData) {
        $videoProcessor = new VideoProcessor();
        $videoProcessor->transcodeVideo($taskData['video_file_id']);
    }
    
    /**
     * 处理切片任务
     */
    private function processSegmentTask($taskData) {
        $videoProcessor = new VideoProcessor();
        $videoProcessor->segmentVideo($taskData['video_file_id']);
    }
    
    /**
     * 处理ASR任务
     */
    private function processAsrTask($taskData) {
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
        $videoAnalysisEngine = new VideoAnalysisEngine();
        $videoAnalysisEngine->processVideoAnalysis($orderId);
    }
}
