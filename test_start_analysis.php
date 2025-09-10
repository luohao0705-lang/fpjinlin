<?php
/**
 * 测试启动分析功能
 */

require_once 'config/database.php';
require_once 'includes/classes/VideoAnalysisOrder.php';

echo "🧪 测试启动分析功能\n";
echo "==================\n\n";

try {
    $db = new Database();
    
    // 1. 创建一个测试订单
    echo "1. 创建测试订单...\n";
    $testFlvUrl = "https://live.douyin.com/test?expire=" . (time() + 3600);
    
    $orderId = $db->insert('video_analysis_orders', [
        'user_id' => 1,
        'live_url' => 'https://live.douyin.com/test',
        'flv_url' => $testFlvUrl,
        'status' => 'reviewing',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "✅ 创建测试订单: ID $orderId\n";
    
    // 2. 创建视频文件记录
    echo "2. 创建视频文件记录...\n";
    $videoFileId = $db->insert('video_files', [
        'order_id' => $orderId,
        'flv_url' => $testFlvUrl,
        'status' => 'pending',
        'recording_status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "✅ 创建视频文件记录: ID $videoFileId\n";
    
    // 3. 测试启动分析
    echo "3. 测试启动分析...\n";
    $videoAnalysisOrder = new VideoAnalysisOrder();
    
    // 模拟启动分析
    $result = $videoAnalysisOrder->startAnalysis($orderId);
    
    if ($result) {
        echo "✅ 启动分析成功\n";
    } else {
        echo "❌ 启动分析失败\n";
    }
    
    // 4. 检查任务是否创建
    echo "4. 检查任务创建情况...\n";
    $tasks = $db->fetchAll("SELECT task_type, status, error_message FROM video_processing_queue WHERE order_id = ?", [$orderId]);
    
    if (empty($tasks)) {
        echo "❌ 没有创建任何任务\n";
    } else {
        echo "✅ 创建了 " . count($tasks) . " 个任务:\n";
        foreach ($tasks as $task) {
            echo "  - 类型: {$task['task_type']}, 状态: {$task['status']}, 错误: " . ($task['error_message'] ?: '无') . "\n";
        }
    }
    
    // 5. 清理测试数据
    echo "5. 清理测试数据...\n";
    $db->query("DELETE FROM video_processing_queue WHERE order_id = ?", [$orderId]);
    $db->query("DELETE FROM video_files WHERE order_id = ?", [$orderId]);
    $db->query("DELETE FROM video_analysis_orders WHERE id = ?", [$orderId]);
    echo "✅ 测试数据已清理\n";
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>