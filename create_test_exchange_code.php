<?php
/**
 * 创建测试兑换码
 */
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = new Database();
    
    // 检查exchange_codes表是否存在
    echo "=== 检查exchange_codes表 ===\n";
    $tables = $db->fetchAll("SHOW TABLES LIKE 'exchange_codes'");
    if (empty($tables)) {
        echo "✗ exchange_codes表不存在，正在创建...\n";
        
        $createTableSql = "
        CREATE TABLE `exchange_codes` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(32) NOT NULL UNIQUE COMMENT '兑换码',
            `value` INT(11) UNSIGNED NOT NULL COMMENT '精灵币数量',
            `batch_no` VARCHAR(32) DEFAULT NULL COMMENT '批次号',
            `is_used` TINYINT(1) DEFAULT 0 COMMENT '是否已使用',
            `used_by` INT(11) UNSIGNED DEFAULT NULL COMMENT '使用者用户ID',
            `used_at` TIMESTAMP NULL COMMENT '使用时间',
            `expires_at` TIMESTAMP NULL COMMENT '过期时间',
            `created_by` INT(11) UNSIGNED DEFAULT NULL COMMENT '创建者管理员ID',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            INDEX `idx_code` (`code`),
            INDEX `idx_batch_no` (`batch_no`),
            INDEX `idx_is_used` (`is_used`),
            INDEX `idx_expires_at` (`expires_at`)
        ) ENGINE=InnoDB COMMENT='兑换码表'";
        
        $db->query($createTableSql);
        echo "✓ exchange_codes表创建成功\n";
    } else {
        echo "✓ exchange_codes表存在\n";
    }
    
    // 创建测试兑换码
    echo "\n=== 创建测试兑换码 ===\n";
    
    $exchangeCode = new ExchangeCode();
    
    // 生成5个测试兑换码，每个价值100精灵币
    $result = $exchangeCode->generateCodes(5, 100, null, null);
    
    echo "✓ 成功创建 {$result['count']} 个兑换码\n";
    echo "批次号: {$result['batchNo']}\n";
    echo "面值: {$result['value']} 精灵币\n\n";
    
    echo "测试兑换码列表:\n";
    foreach ($result['codes'] as $index => $code) {
        echo ($index + 1) . ". {$code}\n";
    }
    
    echo "\n=== 测试完成 ===\n";
    echo "您可以使用上述任意兑换码进行测试！\n";
    
} catch (Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
    echo "详细信息: " . $e->getTraceAsString() . "\n";
}
?>