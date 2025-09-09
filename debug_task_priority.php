<?php
// 调试任务优先级问题
require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>调试任务优先级问题</h2>";

try {
    $orderId = $_GET['order_id'] ?? 27;
    echo "<p>调试订单ID: {$orderId}</p>";
    
    $db = new Database();
    
    // 1. 检查当前任务状态
    echo "<h3>1. 当前任务状态</h3>";
    $tasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? 
         ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    echo "<p>总任务数: " . count($tasks) . "</p>";
    
    foreach ($tasks as $task) {
        echo "<p>任务ID: {$task['id']}, 类型: {$task['task_type']}, 优先级: {$task['priority']}, 状态: {$task['status']}, 创建时间: {$task['created_at']}</p>";
    }
    
    // 2. 检查待处理任务
    echo "<h3>2. 待处理任务</h3>";
    $pendingTasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? AND status = 'pending' 
         ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    echo "<p>待处理任务数: " . count($pendingTasks) . "</p>";
    
    foreach ($pendingTasks as $task) {
        echo "<p>待处理 - 任务ID: {$task['id']}, 类型: {$task['task_type']}, 优先级: {$task['priority']}</p>";
    }
    
    // 3. 检查处理中任务
    echo "<h3>3. 处理中任务</h3>";
    $processingTasks = $db->fetchAll(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? AND status = 'processing' 
         ORDER BY priority DESC, created_at ASC",
        [$orderId]
    );
    
    echo "<p>处理中任务数: " . count($processingTasks) . "</p>";
    
    foreach ($processingTasks as $task) {
        echo "<p>处理中 - 任务ID: {$task['id']}, 类型: {$task['task_type']}, 优先级: {$task['priority']}</p>";
    }
    
    // 4. 检查任务创建时间
    echo "<h3>4. 任务创建时间分析</h3>";
    $timeAnalysis = $db->fetchAll(
        "SELECT task_type, priority, COUNT(*) as count, MIN(created_at) as first_created, MAX(created_at) as last_created
         FROM video_processing_queue 
         WHERE order_id = ? 
         GROUP BY task_type, priority 
         ORDER BY priority DESC",
        [$orderId]
    );
    
    foreach ($timeAnalysis as $analysis) {
        echo "<p>类型: {$analysis['task_type']}, 优先级: {$analysis['priority']}, 数量: {$analysis['count']}, 最早: {$analysis['first_created']}, 最晚: {$analysis['last_created']}</p>";
    }
    
    // 5. 检查是否有重复任务
    echo "<h3>5. 检查重复任务</h3>";
    $duplicates = $db->fetchAll(
        "SELECT task_type, priority, COUNT(*) as count
         FROM video_processing_queue 
         WHERE order_id = ? 
         GROUP BY task_type, priority 
         HAVING COUNT(*) > 1",
        [$orderId]
    );
    
    if (empty($duplicates)) {
        echo "<p>✅ 没有重复任务</p>";
    } else {
        echo "<p>❌ 发现重复任务:</p>";
        foreach ($duplicates as $dup) {
            echo "<p>- 类型: {$dup['task_type']}, 优先级: {$dup['priority']}, 数量: {$dup['count']}</p>";
        }
    }
    
    // 6. 检查第一个应该执行的任务
    echo "<h3>6. 第一个应该执行的任务</h3>";
    $firstTask = $db->fetchOne(
        "SELECT * FROM video_processing_queue 
         WHERE order_id = ? AND status = 'pending' 
         ORDER BY priority DESC, created_at ASC 
         LIMIT 1",
        [$orderId]
    );
    
    if ($firstTask) {
        echo "<p>第一个任务: ID={$firstTask['id']}, 类型={$firstTask['task_type']}, 优先级={$firstTask['priority']}</p>";
    } else {
        echo "<p>❌ 没有待处理的任务</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
