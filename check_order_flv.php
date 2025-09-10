<?php
/**
 * 检查订单FLV地址
 */

require_once 'config/database.php';

echo "🔍 检查订单FLV地址\n";
echo "==================\n\n";

try {
    $db = new Database();
    
    // 获取最近的订单
    $orders = $db->fetchAll(
        "SELECT id, order_no, status, self_flv_url, created_at FROM video_analysis_orders ORDER BY id DESC LIMIT 10"
    );
    
    if (empty($orders)) {
        echo "❌ 没有找到任何订单\n";
        exit;
    }
    
    echo "📋 最近10个订单的FLV地址状态:\n";
    echo "----------------------------------------\n";
    
    foreach ($orders as $order) {
        $flvStatus = empty($order['self_flv_url']) ? '❌ 未填写' : '✅ 已填写';
        $flvUrl = $order['self_flv_url'] ? substr($order['self_flv_url'], 0, 50) . '...' : '无';
        
        echo "订单ID: {$order['id']} | 状态: {$order['status']} | FLV: $flvStatus\n";
        echo "FLV地址: $flvUrl\n";
        echo "创建时间: {$order['created_at']}\n";
        echo "----------------------------------------\n";
    }
    
    // 检查是否有FLV地址的订单
    $ordersWithFlv = $db->fetchAll(
        "SELECT id, order_no, self_flv_url FROM video_analysis_orders WHERE self_flv_url IS NOT NULL AND self_flv_url != '' ORDER BY id DESC LIMIT 5"
    );
    
    if (!empty($ordersWithFlv)) {
        echo "\n✅ 找到有FLV地址的订单，可以测试:\n";
        foreach ($ordersWithFlv as $order) {
            echo "订单ID: {$order['id']} | 订单号: {$order['order_no']}\n";
        }
        
        // 测试第一个有FLV地址的订单
        $testOrderId = $ordersWithFlv[0]['id'];
        echo "\n🧪 测试订单 $testOrderId 的启动分析功能...\n";
        
        require_once 'includes/classes/VideoAnalysisOrder.php';
        $videoOrder = new VideoAnalysisOrder();
        
        try {
            $result = $videoOrder->startAnalysis($testOrderId);
            echo "✅ 启动成功: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        } catch (Exception $e) {
            echo "❌ 启动失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "\n❌ 没有找到有FLV地址的订单\n";
        echo "请先在管理后台为订单填写FLV地址\n";
    }
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}
?>
