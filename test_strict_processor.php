<?php
/**
 * 测试严谨的视频处理系统
 */

require_once 'config/database.php';
require_once 'StrictVideoProcessor.php';

echo "🧪 测试严谨的视频处理系统\n";
echo "==================\n\n";

try {
    $db = new Database();
    $processor = new StrictVideoProcessor();
    
    // 1. 创建测试订单
    echo "1. 创建测试订单...\n";
    $realFlvUrl = 'http://pull-flv-l26.douyincdn.com/stage/stream-117942867085230219_or4.flv?arch_hrchy=w1&exp_hrchy=w1&expire=68ca7511&major_anchor_level=common&sign=8dedf99c273092e6389e3dbbad9ed1b2&t_id=037-20250910164505061DD0AF4B1E4DCD2B27-8zG4Wv&unique_id=stream-117942867085230219_139_flv_or4';
    
    $orderId = $db->insert(
        "INSERT INTO video_analysis_orders (user_id, order_no, title, self_video_link, self_flv_url, cost_coins, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            1, 
            'VA' . date('YmdHis') . rand(1000, 9999),
            '严谨测试订单',
            'https://live.douyin.com/test',
            $realFlvUrl,
            50,
            'reviewing'
        ]
    );
    
    echo "✅ 创建测试订单: ID $orderId\n\n";
    
    // 2. 创建视频文件记录
    echo "2. 创建视频文件记录...\n";
    $videoFileId = $db->insert(
        "INSERT INTO video_files (order_id, video_type, video_index, original_url, flv_url, status, recording_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            $orderId,
            'self',
            0,
            'https://live.douyin.com/test',
            $realFlvUrl,
            'pending',
            'pending'
        ]
    );
    
    echo "✅ 创建视频文件记录: ID $videoFileId\n\n";
    
    // 3. 启动严谨的视频处理
    echo "3. 启动严谨的视频处理...\n";
    $result = $processor->startAnalysis($orderId);
    
    if ($result['success']) {
        echo "✅ 视频处理启动成功\n";
    } else {
        echo "❌ 视频处理启动失败\n";
    }
    
    // 4. 检查处理结果
    echo "\n4. 检查处理结果...\n";
    $tasks = $db->fetchAll(
        "SELECT task_type, status, error_message, started_at, completed_at FROM video_processing_queue WHERE order_id = ? ORDER BY created_at ASC",
        [$orderId]
    );
    
    if (empty($tasks)) {
        echo "❌ 没有创建任何任务\n";
    } else {
        echo "✅ 任务处理情况:\n";
        foreach ($tasks as $task) {
            $status = $task['status'];
            $startTime = $task['started_at'] ?: '未开始';
            $endTime = $task['completed_at'] ?: '未完成';
            $error = $task['error_message'] ?: '无';
            
            echo "  - 类型: {$task['task_type']}, 状态: $status\n";
            echo "    开始时间: $startTime\n";
            echo "    完成时间: $endTime\n";
            echo "    错误信息: $error\n\n";
        }
    }
    
    // 5. 检查视频文件状态
    echo "5. 检查视频文件状态...\n";
    $videoFile = $db->fetchOne(
        "SELECT * FROM video_files WHERE id = ?",
        [$videoFileId]
    );
    
    if ($videoFile) {
        echo "✅ 视频文件状态:\n";
        echo "  - 状态: {$videoFile['status']}\n";
        echo "  - 录制状态: {$videoFile['recording_status']}\n";
        echo "  - 文件路径: " . ($videoFile['file_path'] ?: '无') . "\n";
        echo "  - 文件大小: " . ($videoFile['file_size'] ? $this->formatBytes($videoFile['file_size']) : '无') . "\n";
        echo "  - 视频时长: " . ($videoFile['duration'] ?: '无') . "秒\n";
    }
    
    // 6. 清理测试数据
    echo "\n6. 清理测试数据...\n";
    $db->query("DELETE FROM video_processing_queue WHERE order_id = ?", [$orderId]);
    $db->query("DELETE FROM video_files WHERE order_id = ?", [$orderId]);
    $db->query("DELETE FROM video_analysis_orders WHERE id = ?", [$orderId]);
    echo "✅ 测试数据已清理\n";
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

/**
 * 格式化字节数
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}
?>
