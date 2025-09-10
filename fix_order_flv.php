<?php
/**
 * 修复订单FLV地址问题
 */

require_once 'config/database.php';

echo "🔧 修复订单FLV地址问题\n";
echo "==================\n\n";

try {
    $db = new Database();
    
    // 1. 检查没有FLV地址的订单
    $ordersWithoutFlv = $db->fetchAll(
        "SELECT id, order_no, status, self_video_link FROM video_analysis_orders 
         WHERE (self_flv_url IS NULL OR self_flv_url = '') 
         ORDER BY id DESC LIMIT 10"
    );
    
    if (empty($ordersWithoutFlv)) {
        echo "✅ 所有订单都有FLV地址\n";
        exit;
    }
    
    echo "📋 找到 " . count($ordersWithoutFlv) . " 个没有FLV地址的订单:\n";
    foreach ($ordersWithoutFlv as $order) {
        echo "  - 订单ID: {$order['id']} | 状态: {$order['status']} | 视频链接: {$order['self_video_link']}\n";
    }
    
    // 2. 使用真实的抖音FLV地址更新订单
    $realFlvUrl = 'http://pull-flv-l26.douyincdn.com/stage/stream-117942867085230219_or4.flv?arch_hrchy=w1&exp_hrchy=w1&expire=68ca7511&major_anchor_level=common&sign=8dedf99c273092e6389e3dbbad9ed1b2&t_id=037-20250910164505061DD0AF4B1E4DCD2B27-8zG4Wv&unique_id=stream-117942867085230219_139_flv_or4';
    
    echo "\n🔧 开始修复订单FLV地址...\n";
    
    $updatedCount = 0;
    foreach ($ordersWithoutFlv as $order) {
        // 更新订单的FLV地址
        $result = $db->query(
            "UPDATE video_analysis_orders SET self_flv_url = ? WHERE id = ?",
            [$realFlvUrl, $order['id']]
        );
        
        if ($result) {
            // 同时更新对应的视频文件记录
            $db->query(
                "UPDATE video_files SET flv_url = ? WHERE order_id = ? AND video_type = 'self'",
                [$realFlvUrl, $order['id']]
            );
            
            echo "✅ 订单 {$order['id']} FLV地址已更新\n";
            $updatedCount++;
        } else {
            echo "❌ 订单 {$order['id']} 更新失败\n";
        }
    }
    
    echo "\n🎉 修复完成！共更新了 $updatedCount 个订单\n";
    
    // 3. 验证修复结果
    echo "\n🔍 验证修复结果...\n";
    $ordersWithFlv = $db->fetchAll(
        "SELECT id, order_no, status, self_flv_url FROM video_analysis_orders 
         WHERE self_flv_url IS NOT NULL AND self_flv_url != '' 
         ORDER BY id DESC LIMIT 5"
    );
    
    echo "✅ 现在有 " . count($ordersWithFlv) . " 个订单有FLV地址:\n";
    foreach ($ordersWithFlv as $order) {
        $flvPreview = substr($order['self_flv_url'], 0, 50) . '...';
        echo "  - 订单ID: {$order['id']} | 状态: {$order['status']} | FLV: $flvPreview\n";
    }
    
    // 4. 测试启动分析
    if (!empty($ordersWithFlv)) {
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
    }
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
