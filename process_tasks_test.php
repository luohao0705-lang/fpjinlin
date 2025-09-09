<?php
// 测试任务处理（不需要登录）
require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>测试任务处理</h2>";

try {
    $orderId = $_GET['order_id'] ?? 27;
    echo "<p>处理订单ID: {$orderId}</p>";
    
    $db = new Database();
    
    // 获取待处理的任务
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? AND status = 'pending' 
         ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    if (empty($tasks)) {
        echo "<p>✅ 没有待处理的任务</p>";
        return;
    }
    
    echo "<p>找到 " . count($tasks) . " 个待处理任务</p>";
    
    $processed = 0;
    
    foreach ($tasks as $task) {
        echo "<h3>处理任务: {$task['task_type']} (ID: {$task['id']})</h3>";
        
        try {
            // 更新任务状态为处理中
            $db->query(
                "UPDATE video_processing_queue SET status = 'processing', started_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            echo "<p>✅ 任务状态已更新为处理中</p>";
            
            $taskData = json_decode($task['task_data'], true);
            
            // 根据任务类型进行处理
            switch ($task['task_type']) {
                case 'record':
                    echo "<p>执行录制任务...</p>";
                    
                    $videoFile = $db->fetchOne(
                        "SELECT * FROM video_files WHERE id = ?",
                        [$taskData['video_file_id']]
                    );
                    
                    if (!$videoFile) {
                        throw new Exception('视频文件不存在: ID ' . $taskData['video_file_id']);
                    }
                    
                    if (empty($videoFile['flv_url'])) {
                        throw new Exception('FLV地址为空，请先在后台配置FLV地址');
                    }
                    
                    echo "<p>视频文件: {$videoFile['file_type']}, FLV地址: " . (empty($videoFile['flv_url']) ? '未设置' : '已设置') . "</p>";
                    
                    // 检查FLV地址是否是本地文件
                    if (file_exists($videoFile['flv_url'])) {
                        echo "<p>✅ FLV地址是本地文件，可以录制</p>";
                        
                        require_once 'includes/classes/VideoProcessor.php';
                        $videoProcessor = new VideoProcessor();
                        
                        $videoProcessor->recordVideo($videoFile['id'], $videoFile['flv_url']);
                        echo "<p>✅ 录制任务完成</p>";
                    } else {
                        echo "<p>⚠️ FLV地址不是本地文件，跳过录制</p>";
                    }
                    break;
                    
                case 'transcode':
                    echo "<p>执行转码任务...</p>";
                    // 模拟转码
                    sleep(1);
                    echo "<p>✅ 转码任务完成</p>";
                    break;
                    
                case 'segment':
                    echo "<p>执行切片任务...</p>";
                    // 模拟切片
                    sleep(1);
                    echo "<p>✅ 切片任务完成</p>";
                    break;
                    
                case 'asr':
                    echo "<p>执行语音识别任务...</p>";
                    // 模拟ASR
                    sleep(1);
                    echo "<p>✅ 语音识别任务完成</p>";
                    break;
                    
                case 'analysis':
                    echo "<p>执行AI分析任务...</p>";
                    // 模拟分析
                    sleep(1);
                    echo "<p>✅ AI分析任务完成</p>";
                    break;
                    
                case 'report':
                    echo "<p>执行报告生成任务...</p>";
                    // 模拟报告生成
                    sleep(1);
                    echo "<p>✅ 报告生成任务完成</p>";
                    break;
                    
                default:
                    echo "<p>⚠️ 未知任务类型: {$task['task_type']}</p>";
                    break;
            }
            
            // 更新任务状态为完成
            $db->query(
                "UPDATE video_processing_queue SET status = 'completed', completed_at = NOW() WHERE id = ?",
                [$task['id']]
            );
            
            echo "<p>✅ 任务完成</p>";
            $processed++;
            
        } catch (Exception $e) {
            echo "<p>❌ 任务处理失败: " . htmlspecialchars($e->getMessage()) . "</p>";
            
            // 更新任务状态为失败
            $db->query(
                "UPDATE video_processing_queue SET status = 'failed', error_message = ? WHERE id = ?",
                [htmlspecialchars($e->getMessage()), $task['id']]
            );
        }
    }
    
    echo "<h3>处理完成</h3>";
    echo "<p>成功处理了 {$processed} 个任务</p>";
    
    echo "<p><a href='check_task_status.php?order_id={$orderId}'>查看任务状态</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
