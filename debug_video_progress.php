<?php
/**
 * 调试视频分析进度API
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    echo "开始调试...\n";
    
    // 检查session
    if (!isset($_SESSION['admin_id'])) {
        echo "Session问题: admin_id不存在\n";
        echo "Session内容: " . print_r($_SESSION, true) . "\n";
    } else {
        echo "Session正常: admin_id = " . $_SESSION['admin_id'] . "\n";
    }
    
    $orderId = intval($_GET['order_id'] ?? 23);
    echo "订单ID: {$orderId}\n";
    
    $db = new Database();
    echo "数据库连接成功\n";
    
    // 检查表是否存在
    $tables = $db->fetchAll("SHOW TABLES LIKE 'video_analysis_orders'");
    echo "video_analysis_orders表存在: " . (count($tables) > 0 ? '是' : '否') . "\n";
    
    $tables = $db->fetchAll("SHOW TABLES LIKE 'video_files'");
    echo "video_files表存在: " . (count($tables) > 0 ? '是' : '否') . "\n";
    
    $tables = $db->fetchAll("SHOW TABLES LIKE 'video_processing_queue'");
    echo "video_processing_queue表存在: " . (count($tables) > 0 ? '是' : '否') . "\n";
    
    // 获取订单信息
    $order = $db->fetchOne(
        "SELECT * FROM video_analysis_orders WHERE id = ?",
        [$orderId]
    );
    
    if (!$order) {
        echo "订单不存在\n";
    } else {
        echo "订单存在: " . $order['title'] . "\n";
    }
    
    // 获取视频文件
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
        [$orderId]
    );
    echo "视频文件数量: " . count($videoFiles) . "\n";
    
    // 获取处理任务
    $processingTasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue WHERE order_id = ? ORDER BY created_at DESC",
        [$orderId]
    );
    echo "处理任务数量: " . count($processingTasks) . "\n";
    
    echo "调试完成，没有发现明显错误\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . "\n";
    echo "行号: " . $e->getLine() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}
?>
