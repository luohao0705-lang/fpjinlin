<?php
/**
 * 调试管理后台订单问题
 */

require_once 'config/database.php';

echo "🔍 调试管理后台订单问题\n";
echo "==================\n\n";

try {
    $db = new Database();
    
    // 获取最近的订单
    $orders = $db->fetchAll(
        "SELECT id, order_no, status, self_flv_url, self_video_link, created_at 
         FROM video_analysis_orders 
         ORDER BY id DESC LIMIT 5"
    );
    
    if (empty($orders)) {
        echo "❌ 没有找到任何订单\n";
        exit;
    }
    
    echo "📋 最近的订单:\n";
    echo "----------------------------------------\n";
    
    foreach ($orders as $order) {
        $flvStatus = empty($order['self_flv_url']) ? '❌ 无FLV' : '✅ 有FLV';
        $flvUrl = $order['self_flv_url'] ? substr($order['self_flv_url'], 0, 50) . '...' : '无';
        
        echo "订单ID: {$order['id']} | 状态: {$order['status']} | FLV: $flvStatus\n";
        echo "FLV地址: $flvUrl\n";
        echo "视频链接: {$order['self_video_link']}\n";
        echo "创建时间: {$order['created_at']}\n";
        echo "----------------------------------------\n";
    }
    
    // 测试VideoAnalysisOrder的startAnalysis方法
    if (!empty($orders)) {
        $testOrderId = $orders[0]['id'];
        echo "\n🧪 测试订单 $testOrderId 的startAnalysis方法...\n";
        
        require_once 'includes/classes/VideoAnalysisOrder.php';
        $videoOrder = new VideoAnalysisOrder();
        
        try {
            echo "调用 startAnalysis($testOrderId)...\n";
            $result = $videoOrder->startAnalysis($testOrderId);
            echo "✅ 成功: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        } catch (Exception $e) {
            echo "❌ 失败: " . $e->getMessage() . "\n";
            echo "文件: " . $e->getFile() . "\n";
            echo "行号: " . $e->getLine() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}
?>
