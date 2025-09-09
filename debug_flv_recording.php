<?php
// 调试FLV录制问题
require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>调试FLV录制问题</h2>";

try {
    $orderId = $_GET['order_id'] ?? 27;
    echo "<p>调试订单ID: {$orderId}</p>";
    
    $db = new Database();
    
    // 检查视频文件
    $videoFiles = $db->fetchAll(
        "SELECT * FROM video_files WHERE order_id = ? ORDER BY id",
        [$orderId]
    );
    
    echo "<h3>视频文件详情</h3>";
    foreach ($videoFiles as $file) {
        echo "<p><strong>文件ID: {$file['id']}</strong></p>";
        echo "<p>类型: " . htmlspecialchars($file['file_type']) . "</p>";
        echo "<p>状态: " . htmlspecialchars($file['status']) . "</p>";
        echo "<p>录制进度: {$file['recording_progress']}%</p>";
        echo "<p>FLV地址: " . htmlspecialchars($file['flv_url']) . "</p>";
        
        if (!empty($file['flv_url'])) {
            // 检查FLV地址类型
            if (file_exists($file['flv_url'])) {
                echo "<p>✅ FLV地址是本地文件</p>";
                echo "<p>文件大小: " . formatFileSize(filesize($file['flv_url'])) . "</p>";
            } elseif (strpos($file['flv_url'], 'http') === 0) {
                echo "<p>🌐 FLV地址是网络地址</p>";
                
                // 测试网络地址是否可访问
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 5,
                        'method' => 'HEAD',
                        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ]
                ]);
                
                $headers = @get_headers($file['flv_url'], 1, $context);
                if ($headers) {
                    $statusCode = $headers[0];
                    echo "<p>HTTP状态: " . htmlspecialchars($statusCode) . "</p>";
                } else {
                    echo "<p>❌ 无法访问FLV地址</p>";
                }
            } else {
                echo "<p>❓ FLV地址格式未知</p>";
            }
        } else {
            echo "<p>❌ FLV地址为空</p>";
        }
        echo "<hr>";
    }
    
    // 检查record任务
    $recordTasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? AND task_type = 'record' 
         ORDER BY id",
        [$orderId]
    );
    
    echo "<h3>录制任务详情</h3>";
    foreach ($recordTasks as $task) {
        echo "<p><strong>任务ID: {$task['id']}</strong></p>";
        echo "<p>状态: " . htmlspecialchars($task['status']) . "</p>";
        echo "<p>优先级: {$task['priority']}</p>";
        echo "<p>任务数据: " . htmlspecialchars($task['task_data']) . "</p>";
        
        $taskData = json_decode($task['task_data'], true);
        if ($taskData && isset($taskData['video_file_id'])) {
            $videoFileId = $taskData['video_file_id'];
            echo "<p>关联视频文件ID: {$videoFileId}</p>";
            
            // 查找对应的视频文件
            $videoFile = $db->fetchOne(
                "SELECT * FROM video_files WHERE id = ?",
                [$videoFileId]
            );
            
            if ($videoFile) {
                echo "<p>视频文件状态: " . htmlspecialchars($videoFile['status']) . "</p>";
                echo "<p>视频文件FLV地址: " . htmlspecialchars($videoFile['flv_url']) . "</p>";
            } else {
                echo "<p>❌ 找不到关联的视频文件</p>";
            }
        }
        echo "<hr>";
    }
    
    // 测试录制功能
    echo "<h3>测试录制功能</h3>";
    
    // 找一个有FLV地址的视频文件进行测试
    $testFile = null;
    foreach ($videoFiles as $file) {
        if (!empty($file['flv_url']) && $file['status'] !== 'completed') {
            $testFile = $file;
            break;
        }
    }
    
    if ($testFile) {
        echo "<p>测试文件ID: {$testFile['id']}</p>";
        echo "<p>FLV地址: " . htmlspecialchars($testFile['flv_url']) . "</p>";
        
        // 检查是否是本地文件
        if (file_exists($testFile['flv_url'])) {
            echo "<p>✅ 是本地文件，可以录制</p>";
            
            // 尝试录制
            try {
                require_once 'includes/classes/VideoProcessor.php';
                $videoProcessor = new VideoProcessor();
                
                echo "<p>开始录制测试...</p>";
                $videoProcessor->recordVideo($testFile['id'], $testFile['flv_url']);
                echo "<p>✅ 录制测试成功！</p>";
                
            } catch (Exception $e) {
                echo "<p>❌ 录制测试失败: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p>❌ 不是本地文件，无法录制</p>";
        }
    } else {
        echo "<p>❌ 没有找到可测试的视频文件</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 辅助函数
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
