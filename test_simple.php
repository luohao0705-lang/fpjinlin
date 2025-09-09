<?php
/**
 * 简单测试脚本
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>简单测试</h2>";

try {
    echo "<p>1. 加载配置文件...</p>";
    require_once 'config/config.php';
    echo "<p>✅ 配置文件加载成功</p>";
    
    echo "<p>2. 加载数据库类...</p>";
    require_once 'config/database.php';
    echo "<p>✅ 数据库类加载成功</p>";
    
    echo "<p>3. 创建数据库连接...</p>";
    $db = new Database();
    echo "<p>✅ 数据库连接成功</p>";
    
    echo "<p>4. 检查订单...</p>";
    $orderId = 30;
    $order = $db->fetchOne(
        "SELECT * FROM video_analysis_orders WHERE id = ?",
        [$orderId]
    );
    
    if (!$order) {
        echo "<p>❌ 订单不存在</p>";
        exit;
    }
    
    echo "<p>✅ 订单存在: " . htmlspecialchars($order['title']) . "</p>";
    echo "<p>订单状态: " . htmlspecialchars($order['status']) . "</p>";
    
    echo "<p>5. 检查视频文件...</p>";
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
        [$orderId]
    );
    
    echo "<p>视频文件数量: " . count($videoFiles) . "</p>";
    foreach ($videoFiles as $file) {
        echo "<p>文件ID: {$file['id']}, 类型: {$file['video_type']}, FLV地址: " . 
             (empty($file['flv_url']) ? '未设置' : '已设置') . "</p>";
    }
    
    echo "<p>6. 加载VideoAnalysisOrder类...</p>";
    require_once 'includes/classes/VideoAnalysisOrder.php';
    echo "<p>✅ VideoAnalysisOrder类加载成功</p>";
    
    echo "<p>7. 创建VideoAnalysisOrder实例...</p>";
    $videoAnalysisOrder = new VideoAnalysisOrder();
    echo "<p>✅ VideoAnalysisOrder实例创建成功</p>";
    
    echo "<p>8. 测试startAnalysis方法...</p>";
    $result = $videoAnalysisOrder->startAnalysis($orderId);
    echo "<p>✅ startAnalysis方法执行成功</p>";
    echo "<p>结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 异常: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>文件: " . $e->getFile() . "</p>";
    echo "<p>行号: " . $e->getLine() . "</p>";
} catch (Error $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>文件: " . $e->getFile() . "</p>";
    echo "<p>行号: " . $e->getLine() . "</p>";
}
?>
