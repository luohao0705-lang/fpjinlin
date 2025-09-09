<?php
/**
 * 调试启动分析功能
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>调试启动分析功能</h2>";

try {
    require_once 'config/config.php';
    require_once 'config/database.php';
    require_once 'includes/classes/VideoAnalysisOrder.php';
    
    $orderId = $_GET['order_id'] ?? 30;
    echo "<p>测试订单ID: {$orderId}</p>";
    
    $db = new Database();
    
    // 检查订单状态
    $order = $db->fetchOne(
        "SELECT * FROM video_analysis_orders WHERE id = ?",
        [$orderId]
    );
    
    if (!$order) {
        echo "<p>❌ 订单不存在</p>";
        exit;
    }
    
    echo "<p>订单标题: " . htmlspecialchars($order['title']) . "</p>";
    echo "<p>订单状态: " . htmlspecialchars($order['status']) . "</p>";
    
    // 检查视频文件
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
        [$orderId]
    );
    
    echo "<p>视频文件数量: " . count($videoFiles) . "</p>";
    foreach ($videoFiles as $file) {
        echo "<p>文件ID: {$file['id']}, 类型: {$file['video_type']}, FLV地址: " . 
             (empty($file['flv_url']) ? '未设置' : '已设置') . "</p>";
    }
    
    // 检查FLV地址是否都已填写
    $emptyFlvFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? AND (flv_url IS NULL OR flv_url = '')",
        [$orderId]
    );
    
    if (!empty($emptyFlvFiles)) {
        echo "<p>❌ 还有 " . count($emptyFlvFiles) . " 个视频文件没有填写FLV地址</p>";
        foreach ($emptyFlvFiles as $file) {
            echo "<p>文件ID: {$file['id']}, 类型: {$file['video_type']}</p>";
        }
        exit;
    }
    
    // 检查处理任务
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue WHERE order_id = ? ORDER BY priority DESC",
        [$orderId]
    );
    
    echo "<p>现有处理任务数量: " . count($tasks) . "</p>";
    foreach ($tasks as $task) {
        echo "<p>任务ID: {$task['id']}, 类型: {$task['task_type']}, 优先级: {$task['priority']}, 状态: {$task['status']}</p>";
    }
    
    // 测试启动分析
    echo "<h3>测试启动分析</h3>";
    
    $videoAnalysisOrder = new VideoAnalysisOrder();
    $result = $videoAnalysisOrder->startAnalysis($orderId);
    
    echo "<p>✅ 启动分析成功</p>";
    echo "<p>结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "</p>";
    
    // 检查任务状态
    $tasksAfter = $db->fetchAll(
        "SELECT * FROM video_processing_queue WHERE order_id = ? ORDER BY priority DESC",
        [$orderId]
    );
    
    echo "<h3>启动后的任务状态</h3>";
    echo "<p>任务数量: " . count($tasksAfter) . "</p>";
    foreach ($tasksAfter as $task) {
        echo "<p>任务ID: {$task['id']}, 类型: {$task['task_type']}, 优先级: {$task['priority']}, 状态: {$task['status']}</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>文件: " . $e->getFile() . "</p>";
    echo "<p>行号: " . $e->getLine() . "</p>";
    echo "<p>堆栈: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
} catch (Error $e) {
    echo "<p style='color: red;'>❌ 致命错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>文件: " . $e->getFile() . "</p>";
    echo "<p>行号: " . $e->getLine() . "</p>";
}
?>
