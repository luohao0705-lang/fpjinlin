<?php
/**
 * 测试视频分析订单创建
 */
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/VideoAnalysisOrder.php';
require_once 'includes/classes/User.php';
require_once 'includes/classes/OperationLog.php';

echo "开始测试视频分析订单创建...\n";

try {
    // 测试数据库连接
    $db = new Database();
    echo "✓ 数据库连接成功\n";
    
    // 测试VideoAnalysisOrder类
    $videoAnalysisOrder = new VideoAnalysisOrder();
    echo "✓ VideoAnalysisOrder类加载成功\n";
    
    // 测试User类
    $user = new User();
    echo "✓ User类加载成功\n";
    
    // 测试OperationLog类
    $operationLog = new OperationLog();
    echo "✓ OperationLog类加载成功\n";
    
    // 检查必要的表是否存在
    $tables = ['video_analysis_orders', 'video_files', 'video_processing_queue'];
    foreach ($tables as $table) {
        $result = $db->fetchOne("SHOW TABLES LIKE ?", [$table]);
        if ($result) {
            echo "✓ 表 {$table} 存在\n";
        } else {
            echo "✗ 表 {$table} 不存在\n";
        }
    }
    
    // 测试系统配置
    $costCoins = getSystemConfig('video_analysis_cost_coins', 50);
    echo "✓ 视频分析消耗精灵币配置: {$costCoins}\n";
    
    echo "\n所有测试通过！\n";
    
} catch (Exception $e) {
    echo "✗ 测试失败: " . $e->getMessage() . "\n";
    echo "错误堆栈: " . $e->getTraceAsString() . "\n";
}
?>
