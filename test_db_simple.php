<?php
// 简单的数据库连接测试
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "开始测试数据库连接...\n";

try {
    require_once 'config/config.php';
    require_once 'config/database.php';
    
    echo "1. 配置文件加载成功\n";
    
    $db = new Database();
    echo "2. Database类实例化成功\n";
    
    $connection = $db->getConnection();
    echo "3. 数据库连接成功\n";
    
    // 测试简单查询
    $result = $db->fetchOne("SELECT 1 as test");
    echo "4. 简单查询成功: " . $result['test'] . "\n";
    
    // 测试video_analysis_orders表
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM video_analysis_orders");
    echo "5. video_analysis_orders表查询成功: " . $result['count'] . " 条记录\n";
    
    // 测试插入操作
    $testData = [
        'user_id' => 1,
        'order_no' => 'TEST' . time(),
        'title' => '测试订单',
        'self_video_link' => 'https://test.com',
        'competitor_video_links' => json_encode(['https://test1.com', 'https://test2.com']),
        'cost_coins' => 50
    ];
    
    $orderId = $db->insert(
        "INSERT INTO video_analysis_orders (user_id, order_no, title, self_video_link, competitor_video_links, cost_coins, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
        [
            $testData['user_id'],
            $testData['order_no'],
            $testData['title'],
            $testData['self_video_link'],
            $testData['competitor_video_links'],
            $testData['cost_coins']
        ]
    );
    
    echo "6. 测试插入成功，订单ID: " . $orderId . "\n";
    
    // 清理测试数据
    $db->query("DELETE FROM video_analysis_orders WHERE id = ?", [$orderId]);
    echo "7. 测试数据清理完成\n";
    
    echo "\n所有测试通过！\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . "\n";
    echo "行号: " . $e->getLine() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}
?>
