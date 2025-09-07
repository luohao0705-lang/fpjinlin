<?php
/**
 * 创建缺失的数据库表
 */
require_once 'config/config.php';
require_once 'config/database.php';

echo "=== 创建缺失的数据库表 ===\n";

try {
    $db = new Database();
    
    // 检查并创建 error_logs 表
    echo "检查 error_logs 表...\n";
    $tables = $db->fetchAll("SHOW TABLES LIKE 'error_logs'");
    
    if (empty($tables)) {
        echo "创建 error_logs 表...\n";
        $createErrorLogsTable = "
        CREATE TABLE `error_logs` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `error_type` VARCHAR(50) NOT NULL COMMENT '错误类型',
            `error_message` TEXT NOT NULL COMMENT '错误消息',
            `file_path` VARCHAR(255) DEFAULT NULL COMMENT '错误文件路径',
            `line_number` INT(11) DEFAULT NULL COMMENT '错误行号',
            `user_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '用户ID',
            `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
            `user_agent` TEXT COMMENT '用户代理',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            INDEX `idx_error_type` (`error_type`),
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB COMMENT='错误日志表'";
        
        $db->query($createErrorLogsTable);
        echo "✓ error_logs 表创建成功\n";
    } else {
        echo "✓ error_logs 表已存在\n";
    }
    
    // 检查其他可能缺失的表
    $requiredTables = [
        'users' => '用户表',
        'analysis_orders' => '分析订单表', 
        'coin_transactions' => '精灵币交易表',
        'exchange_codes' => '兑换码表',
        'operation_logs' => '操作日志表',
        'admins' => '管理员表'
    ];
    
    echo "\n检查其他必要的表...\n";
    foreach ($requiredTables as $tableName => $description) {
        $tables = $db->fetchAll("SHOW TABLES LIKE '{$tableName}'");
        if (empty($tables)) {
            echo "✗ {$description} ({$tableName}) 不存在\n";
        } else {
            $count = $db->fetchOne("SELECT COUNT(*) as count FROM {$tableName}")['count'];
            echo "✓ {$description} ({$tableName}) 存在，记录数: {$count}\n";
        }
    }
    
    echo "\n=== 完成 ===\n";
    
} catch (Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
    echo "详细信息: " . $e->getTraceAsString() . "\n";
}
?>