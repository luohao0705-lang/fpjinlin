<?php
/**
 * 测试启动分析功能
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
require_once 'config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 设置admin_id用于测试
$_SESSION['admin_id'] = 1;

$orderId = intval($_GET['order_id'] ?? 24);

echo "<h2>测试启动分析功能 - 订单ID: {$orderId}</h2>";

try {
    echo "<h3>1. 检查订单是否存在</h3>";
    $db = new Database();
    $order = $db->fetchOne("SELECT * FROM video_analysis_orders WHERE id = ?", [$orderId]);
    if (!$order) {
        throw new Exception("订单不存在");
    }
    echo "✅ 订单存在: " . $order['title'] . "<br>";
    
    echo "<h3>2. 检查视频文件</h3>";
    $videoFiles = $db->fetchAll("SELECT * FROM video_files WHERE order_id = ?", [$orderId]);
    echo "✅ 视频文件数量: " . count($videoFiles) . "<br>";
    
    echo "<h3>3. 检查处理任务</h3>";
    $tasks = $db->fetchAll("SELECT * FROM video_processing_queue WHERE order_id = ?", [$orderId]);
    echo "✅ 处理任务数量: " . count($tasks) . "<br>";
    
    echo "<h3>4. 测试VideoAnalysisOrder类</h3>";
    require_once 'includes/classes/VideoAnalysisOrder.php';
    $videoAnalysisOrder = new VideoAnalysisOrder();
    echo "✅ VideoAnalysisOrder类加载成功<br>";
    
    echo "<h3>5. 测试startAnalysis方法</h3>";
    $result = $videoAnalysisOrder->startAnalysis($orderId);
    echo "✅ startAnalysis执行成功<br>";
    echo "结果: " . json_encode($result) . "<br>";
    
    echo "<h3>6. 检查任务状态变化</h3>";
    $tasksAfter = $db->fetchAll("SELECT * FROM video_processing_queue WHERE order_id = ? ORDER BY priority DESC", [$orderId]);
    echo "任务状态变化:<br>";
    foreach ($tasksAfter as $task) {
        echo "- {$task['task_type']}: {$task['status']}<br>";
    }
    
    echo "<h3>✅ 测试完成，没有发现错误</h3>";
    
} catch (Exception $e) {
    echo "<h3>❌ 发现错误</h3>";
    echo "<strong>错误类型:</strong> Exception<br>";
    echo "<strong>错误信息:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>文件:</strong> " . $e->getFile() . "<br>";
    echo "<strong>行号:</strong> " . $e->getLine() . "<br>";
    echo "<strong>堆栈跟踪:</strong><br><pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<h3>❌ 发现致命错误</h3>";
    echo "<strong>错误类型:</strong> Fatal Error<br>";
    echo "<strong>错误信息:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>文件:</strong> " . $e->getFile() . "<br>";
    echo "<strong>行号:</strong> " . $e->getLine() . "<br>";
    echo "<strong>堆栈跟踪:</strong><br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>
