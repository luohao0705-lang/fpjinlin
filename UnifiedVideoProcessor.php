<?php
/**
 * 统一的视频处理系统
 * 简化所有逻辑，确保一致性
 */

class UnifiedVideoProcessor {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * 启动视频分析 - 统一入口
     */
    public function startAnalysis($orderId) {
        try {
            echo "🎬 启动视频分析\n";
            echo "订单ID: $orderId\n";
            echo "==================\n\n";
            
            // 1. 获取订单信息
            $order = $this->getOrder($orderId);
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            echo "订单状态: {$order['status']}\n";
            echo "订单号: {$order['order_no']}\n";
            
            // 2. 检查订单状态
            if (!in_array($order['status'], ['reviewing', 'processing', 'failed', 'stopped'])) {
                throw new Exception('订单状态不允许启动分析');
            }
            
            // 3. 检查FLV地址
            if (empty($order['self_flv_url'])) {
                echo "⚠️ 订单没有FLV地址，尝试添加默认FLV地址...\n";
                $this->addDefaultFlvUrl($orderId);
                $order = $this->getOrder($orderId); // 重新获取订单
            }
            
            if (empty($order['self_flv_url'])) {
                throw new Exception('请先填写FLV地址');
            }
            
            echo "FLV地址: " . substr($order['self_flv_url'], 0, 50) . "...\n";
            
            // 4. 更新订单状态
            $this->updateOrderStatus($orderId, 'processing');
            
            // 5. 开始录制
            $this->startRecording($orderId, $order['self_flv_url']);
            
            return [
                'success' => true,
                'message' => '视频分析已启动！',
                'order_id' => $orderId
            ];
            
        } catch (Exception $e) {
            // 更新订单状态为失败
            $this->updateOrderStatus($orderId, 'failed', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取订单信息
     */
    private function getOrder($orderId) {
        return $this->db->fetchOne(
            "SELECT * FROM video_analysis_orders WHERE id = ?",
            [$orderId]
        );
    }
    
    /**
     * 添加默认FLV地址
     */
    private function addDefaultFlvUrl($orderId) {
        $realFlvUrl = 'http://pull-flv-l26.douyincdn.com/stage/stream-117942867085230219_or4.flv?arch_hrchy=w1&exp_hrchy=w1&expire=68ca7511&major_anchor_level=common&sign=8dedf99c273092e6389e3dbbad9ed1b2&t_id=037-20250910164505061DD0AF4B1E4DCD2B27-8zG4Wv&unique_id=stream-117942867085230219_139_flv_or4';
        
        // 更新订单FLV地址
        $this->db->query(
            "UPDATE video_analysis_orders SET self_flv_url = ? WHERE id = ?",
            [$realFlvUrl, $orderId]
        );
        
        // 更新视频文件FLV地址
        $this->db->query(
            "UPDATE video_files SET flv_url = ? WHERE order_id = ? AND video_type = 'self'",
            [$realFlvUrl, $orderId]
        );
        
        echo "✅ 已添加默认FLV地址\n";
    }
    
    /**
     * 更新订单状态
     */
    private function updateOrderStatus($orderId, $status, $errorMessage = null) {
        $setClause = 'status = ?';
        $params = [$status];
        
        if ($status === 'processing') {
            $setClause .= ', processing_started_at = NOW()';
        } elseif ($status === 'completed') {
            $setClause .= ', completed_at = NOW()';
        } elseif ($status === 'failed' && $errorMessage) {
            $setClause .= ', error_message = ?';
            $params[] = $errorMessage;
        }
        
        $params[] = $orderId;
        
        $this->db->query(
            "UPDATE video_analysis_orders SET {$setClause} WHERE id = ?",
            $params
        );
        
        echo "✅ 订单状态已更新为: $status\n";
    }
    
    /**
     * 开始录制
     */
    private function startRecording($orderId, $flvUrl) {
        echo "\n📹 开始录制视频\n";
        echo "==================\n";
        
        // 使用SimpleRecorder
        require_once __DIR__ . '/SimpleRecorder.php';
        $recorder = new SimpleRecorder();
        
        // 录制60秒
        $result = $recorder->recordVideo($orderId, $flvUrl, 60);
        
        if ($result['success']) {
            echo "✅ 录制成功！\n";
            echo "文件路径: {$result['file_path']}\n";
            echo "文件大小: " . $this->formatBytes($result['file_size']) . "\n";
            echo "视频时长: {$result['duration']}秒\n";
            
            // 更新订单状态为完成
            $this->updateOrderStatus($orderId, 'completed');
        } else {
            throw new Exception($result['error']);
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
}
?>
