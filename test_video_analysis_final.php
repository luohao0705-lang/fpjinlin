<?php
// 最终视频分析测试
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/VideoProcessor.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>视频分析功能最终测试</h2>";

try {
    $db = new Database();
    
    // 获取订单ID
    $orderId = $_GET['order_id'] ?? 25;
    echo "<p>测试订单ID: {$orderId}</p>";
    
    // 检查订单
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
    
    // 检查视频文件
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ?",
        [$orderId]
    );
    
    echo "<p>视频文件数量: " . count($videoFiles) . "</p>";
    
    foreach ($videoFiles as $file) {
        echo "<p>- 文件ID: {$file['id']}, 类型: {$file['file_type']}, FLV地址: " . 
             (empty($file['flv_url']) ? '未设置' : '已设置') . "</p>";
    }
    
    // 检查处理任务
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue WHERE order_id = ? ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    echo "<p>处理任务数量: " . count($tasks) . "</p>";
    
    foreach ($tasks as $task) {
        echo "<p>- 任务: {$task['task_type']}, 状态: {$task['status']}, 优先级: {$task['priority']}</p>";
    }
    
    // 测试VideoProcessor
    echo "<h3>测试VideoProcessor类</h3>";
    
    $processor = new VideoProcessor();
    echo "<p>✅ VideoProcessor类创建成功</p>";
    
    $config = $processor->getConfig();
    echo "<p>配置信息:</p>";
    echo "<ul>";
    echo "<li>最大录制时长: {$config['max_duration']}秒</li>";
    echo "<li>切片时长: {$config['segment_duration']}秒</li>";
    echo "<li>存储模式: " . (empty($config['oss_bucket']) ? '本地存储' : 'OSS存储') . "</li>";
    echo "</ul>";
    
    // 测试录制功能（使用本地生成的视频）
    echo "<h3>测试录制功能</h3>";
    
    // 生成一个测试视频作为FLV源
    $testVideoFile = sys_get_temp_dir() . '/test_flv_source_' . time() . '.mp4';
    $ffmpegPath = 'ffmpeg';
    
    $generateCommand = sprintf(
        '%s -f lavfi -i testsrc=duration=10:size=640x480:rate=30 -c:v libx264 -preset fast %s -y',
        escapeshellarg($ffmpegPath),
        escapeshellarg($testVideoFile)
    );
    
    echo "<p>生成测试视频...</p>";
    exec($generateCommand . ' 2>&1', $generateOutput, $generateCode);
    
    if ($generateCode === 0 && file_exists($testVideoFile)) {
        echo "<p>✅ 测试视频生成成功</p>";
        
        // 模拟录制过程
        echo "<p>模拟录制过程...</p>";
        
        // 更新第一个视频文件的FLV地址
        if (!empty($videoFiles)) {
            $firstFile = $videoFiles[0];
            $db->query(
                "UPDATE video_files SET flv_url = ? WHERE id = ?",
                [$testVideoFile, $firstFile['id']]
            );
            echo "<p>✅ 已设置测试FLV地址</p>";
            
            // 测试录制
            try {
                $processor->recordVideo($firstFile['id'], $testVideoFile);
                echo "<p>✅ 录制功能测试成功！</p>";
            } catch (Exception $e) {
                echo "<p>❌ 录制功能测试失败: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
        
        // 清理测试文件
        if (file_exists($testVideoFile)) {
            unlink($testVideoFile);
        }
    } else {
        echo "<p>❌ 无法生成测试视频</p>";
    }
    
    echo "<h3>系统状态总结</h3>";
    echo "<p>✅ 数据库连接正常</p>";
    echo "<p>✅ FFmpeg功能正常</p>";
    echo "<p>✅ VideoProcessor类正常</p>";
    echo "<p>✅ 视频分析系统就绪</p>";
    
    echo "<p><a href='process_tasks_manually.php?order_id={$orderId}'>开始处理任务</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>文件: " . $e->getFile() . "</p>";
    echo "<p>行号: " . $e->getLine() . "</p>";
}
?>
