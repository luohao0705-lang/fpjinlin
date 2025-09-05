<?php
/**
 * 测试创建分析订单功能
 */
require_once 'config/config.php';
require_once 'config/database.php';

echo "=== 测试创建分析订单 ===\n";

try {
    // 1. 测试数据库连接
    echo "1. 测试数据库连接...\n";
    $db = new Database();
    $connection = $db->getConnection();
    echo "✓ 数据库连接成功\n";
    
    // 2. 检查必要的表
    echo "\n2. 检查数据库表...\n";
    $tables = ['users', 'analysis_orders', 'coin_transactions'];
    foreach ($tables as $table) {
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM $table");
        echo "✓ 表 $table 存在，记录数: " . $result['count'] . "\n";
    }
    
    // 3. 测试系统配置
    echo "\n3. 测试系统配置...\n";
    try {
        $costCoins = getSystemConfig('analysis_cost_coins', DEFAULT_ANALYSIS_COST);
        echo "✓ 分析费用配置: {$costCoins} 精灵币\n";
    } catch (Exception $e) {
        echo "⚠ 系统配置获取失败，使用默认值: " . DEFAULT_ANALYSIS_COST . " 精灵币\n";
        echo "错误: " . $e->getMessage() . "\n";
    }
    
    // 4. 测试用户类
    echo "\n4. 测试用户类...\n";
    $user = new User();
    echo "✓ User类实例化成功\n";
    
    // 5. 测试分析订单类
    echo "\n5. 测试分析订单类...\n";
    $analysisOrder = new AnalysisOrder();
    echo "✓ AnalysisOrder类实例化成功\n";
    
    // 6. 测试创建订单（使用测试数据）
    echo "\n6. 测试创建订单...\n";
    
    // 先创建一个测试用户（如果不存在）
    $testUser = $user->getUserByPhone('13800138000');
    if (!$testUser) {
        echo "创建测试用户...\n";
        $userId = $user->register('13800138000', 'test123', '010705');
        echo "✓ 测试用户创建成功，ID: {$userId}\n";
    } else {
        $userId = $testUser['id'];
        echo "✓ 使用现有测试用户，ID: {$userId}\n";
    }
    
    // 确保用户有足够的精灵币
    $userInfo = $user->getUserById($userId);
    $currentCoins = $userInfo['jingling_coins'];
    echo "当前精灵币: {$currentCoins}\n";
    
    if ($currentCoins < DEFAULT_ANALYSIS_COST) {
        echo "为测试用户充值精灵币...\n";
        $user->rechargeCoins($userId, DEFAULT_ANALYSIS_COST * 2, null, '测试充值');
        echo "✓ 充值成功\n";
    }
    
    // 测试创建订单
    $testData = [
        'title' => '测试分析订单 - ' . date('Y-m-d H:i:s'),
        'selfScript' => '欢迎大家来到我的直播间，今天给大家带来超值好货...',
        'competitorScripts' => [
            '同行1话术：大家好，欢迎来到直播间...',
            '同行2话术：今天的产品非常优惠...',
            '同行3话术：限时特价，机不可失...'
        ]
    ];
    
    $result = $analysisOrder->createOrder(
        $userId,
        $testData['title'],
        $testData['selfScript'],
        $testData['competitorScripts']
    );
    
    echo "✓ 订单创建成功！\n";
    echo "订单ID: " . $result['orderId'] . "\n";
    echo "订单号: " . $result['orderNo'] . "\n";
    echo "消费: " . $result['costCoins'] . " 精灵币\n";
    
    echo "\n=== 测试完成 ===\n";
    echo "所有功能正常，可以正常创建分析订单！\n";
    
} catch (Exception $e) {
    echo "\n✗ 测试失败: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
}
?>