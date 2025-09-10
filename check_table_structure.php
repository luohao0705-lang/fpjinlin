<?php
/**
 * 检查数据库表结构
 */

require_once 'config/database.php';

echo "🔍 检查数据库表结构\n";
echo "==================\n\n";

try {
    $db = new Database();
    
    // 1. 检查video_analysis_orders表结构
    echo "1. video_analysis_orders表结构:\n";
    $columns = $db->fetchAll("SHOW COLUMNS FROM video_analysis_orders");
    foreach ($columns as $column) {
        echo "  - {$column['Field']}: {$column['Type']}\n";
    }
    
    // 2. 检查video_files表结构
    echo "\n2. video_files表结构:\n";
    $columns = $db->fetchAll("SHOW COLUMNS FROM video_files");
    foreach ($columns as $column) {
        echo "  - {$column['Field']}: {$column['Type']}\n";
    }
    
    // 3. 检查video_processing_queue表结构
    echo "\n3. video_processing_queue表结构:\n";
    $columns = $db->fetchAll("SHOW COLUMNS FROM video_processing_queue");
    foreach ($columns as $column) {
        echo "  - {$column['Field']}: {$column['Type']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}
?>
