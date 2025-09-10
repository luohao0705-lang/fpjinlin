<?php
/**
 * 数据库修复执行脚本 V2
 * 修复事务管理问题，更安全可靠
 */

require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "开始执行数据库修复 V2...\n";
    
    // 1. 修复状态枚举不一致问题
    echo "1. 修复状态枚举...\n";
    try {
        $pdo->exec("ALTER TABLE `video_files` 
            MODIFY COLUMN `recording_status` enum('pending','recording','completed','failed','stopped') DEFAULT 'pending' COMMENT '录制状态'");
        echo "   - recording_status 枚举修复成功\n";
    } catch (PDOException $e) {
        echo "   - recording_status 枚举修复失败: " . $e->getMessage() . "\n";
    }
    
    // 2. 添加关键索引
    echo "2. 添加索引...\n";
    
    // 检查并添加 idx_recording_status 索引
    $stmt = $pdo->query("SHOW INDEX FROM `video_files` WHERE Key_name = 'idx_recording_status'");
    if ($stmt->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE `video_files` ADD KEY `idx_recording_status` (`recording_status`)");
            echo "   - idx_recording_status 索引创建成功\n";
        } catch (PDOException $e) {
            echo "   - idx_recording_status 索引创建失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   - idx_recording_status 索引已存在，跳过\n";
    }
    
    // 检查并添加 idx_created_at 索引
    $stmt = $pdo->query("SHOW INDEX FROM `video_processing_queue` WHERE Key_name = 'idx_created_at'");
    if ($stmt->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE `video_processing_queue` ADD KEY `idx_created_at` (`created_at`)");
            echo "   - idx_created_at 索引创建成功\n";
        } catch (PDOException $e) {
            echo "   - idx_created_at 索引创建失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   - idx_created_at 索引已存在，跳过\n";
    }
    
    // 3. 清理无效数据
    echo "3. 清理无效数据...\n";
    
    // 清理无效的 video_files 记录
    try {
        $stmt = $pdo->prepare("DELETE vf FROM `video_files` vf 
            LEFT JOIN `video_analysis_orders` vao ON vf.order_id = vao.id 
            WHERE vao.id IS NULL");
        $stmt->execute();
        $deleted_files = $stmt->rowCount();
        echo "   - 删除了 {$deleted_files} 条无效的 video_files 记录\n";
    } catch (PDOException $e) {
        echo "   - 清理 video_files 失败: " . $e->getMessage() . "\n";
    }
    
    // 清理无效的 video_processing_queue 记录
    try {
        $stmt = $pdo->prepare("DELETE vpq FROM `video_processing_queue` vpq 
            LEFT JOIN `video_analysis_orders` vao ON vpq.order_id = vao.id 
            WHERE vao.id IS NULL");
        $stmt->execute();
        $deleted_queue = $stmt->rowCount();
        echo "   - 删除了 {$deleted_queue} 条无效的 video_processing_queue 记录\n";
    } catch (PDOException $e) {
        echo "   - 清理 video_processing_queue 失败: " . $e->getMessage() . "\n";
    }
    
    // 4. 添加有用的字段
    echo "4. 添加新字段...\n";
    
    // 检查并添加 file_hash 字段
    $stmt = $pdo->query("SHOW COLUMNS FROM `video_files` LIKE 'file_hash'");
    if ($stmt->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE `video_files` 
                ADD COLUMN `file_hash` varchar(64) DEFAULT NULL COMMENT '文件MD5哈希值' AFTER `file_size`");
            echo "   - 添加了 file_hash 字段\n";
        } catch (PDOException $e) {
            echo "   - file_hash 字段添加失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   - file_hash 字段已存在，跳过\n";
    }
    
    // 检查并添加 retry_count 字段
    $stmt = $pdo->query("SHOW COLUMNS FROM `video_files` LIKE 'retry_count'");
    if ($stmt->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE `video_files` 
                ADD COLUMN `retry_count` tinyint(3) DEFAULT '0' COMMENT '重试次数' AFTER `error_message`");
            echo "   - 添加了 retry_count 字段\n";
        } catch (PDOException $e) {
            echo "   - retry_count 字段添加失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   - retry_count 字段已存在，跳过\n";
    }
    
    // 检查并添加 max_retries 字段
    $stmt = $pdo->query("SHOW COLUMNS FROM `video_files` LIKE 'max_retries'");
    if ($stmt->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE `video_files` 
                ADD COLUMN `max_retries` tinyint(3) DEFAULT '3' COMMENT '最大重试次数' AFTER `retry_count`");
            echo "   - 添加了 max_retries 字段\n";
        } catch (PDOException $e) {
            echo "   - max_retries 字段添加失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   - max_retries 字段已存在，跳过\n";
    }
    
    // 5. 验证修复结果
    echo "5. 验证修复结果...\n";
    
    // 检查表结构
    $stmt = $pdo->query("SHOW COLUMNS FROM `video_files` LIKE 'recording_status'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($column && strpos($column['Type'], 'stopped') !== false) {
        echo "   - recording_status 枚举修复成功\n";
    } else {
        echo "   - recording_status 枚举修复失败\n";
    }
    
    // 检查索引
    $stmt = $pdo->query("SHOW INDEX FROM `video_files` WHERE Key_name = 'idx_recording_status'");
    if ($stmt->rowCount() > 0) {
        echo "   - idx_recording_status 索引创建成功\n";
    } else {
        echo "   - idx_recording_status 索引创建失败\n";
    }
    
    // 检查数据一致性
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM `video_analysis_orders`");
    $orders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM `video_files`");
    $files = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM `video_processing_queue`");
    $queue = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "   - 数据统计: 订单 {$orders} 个, 视频文件 {$files} 个, 队列任务 {$queue} 个\n";
    
    // 6. 检查外键约束
    echo "6. 检查外键约束...\n";
    $stmt = $pdo->query("SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE REFERENCED_TABLE_SCHEMA = DATABASE() 
    AND REFERENCED_TABLE_NAME IS NOT NULL");
    
    $foreign_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   - 找到 " . count($foreign_keys) . " 个外键约束\n";
    
    foreach ($foreign_keys as $fk) {
        echo "     * {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
    }
    
    echo "\n✅ 数据库修复 V2 完成！\n";
    echo "修复内容:\n";
    echo "- 统一了状态枚举值\n";
    echo "- 添加了关键索引\n";
    echo "- 清理了无效数据\n";
    echo "- 添加了有用的字段\n";
    echo "- 检查了外键约束\n";
    
} catch (Exception $e) {
    echo "\n❌ 数据库修复失败: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . "\n";
    echo "错误行号: " . $e->getLine() . "\n";
    
    // 记录错误日志
    error_log("Database fix V2 failed: " . $e->getMessage());
}
?>
