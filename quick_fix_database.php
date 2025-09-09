<?php
/**
 * 快速修复数据库字段问题
 * 解决 "Data truncated for column 'status'" 错误
 */
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = new Database();
    
    echo "<h2>快速修复数据库字段问题</h2>";
    
    // 1. 修复 video_processing_queue 表的 status 字段
    echo "<h3>1. 修复 video_processing_queue 表的 status 字段</h3>";
    try {
        $db->query("
            ALTER TABLE `video_processing_queue` 
            MODIFY COLUMN `status` ENUM('pending', 'processing', 'completed', 'failed', 'retry', 'record') DEFAULT 'pending' COMMENT '状态'
        ");
        echo "✅ video_processing_queue.status 字段修复成功<br>";
    } catch (Exception $e) {
        echo "⚠️ video_processing_queue.status 字段修复失败: " . $e->getMessage() . "<br>";
    }
    
    // 2. 修复 video_processing_queue 表的 task_type 字段
    echo "<h3>2. 修复 video_processing_queue 表的 task_type 字段</h3>";
    try {
        $db->query("
            ALTER TABLE `video_processing_queue` 
            MODIFY COLUMN `task_type` ENUM('record', 'download', 'transcode', 'segment', 'asr', 'analysis', 'report') NOT NULL COMMENT '任务类型'
        ");
        echo "✅ video_processing_queue.task_type 字段修复成功<br>";
    } catch (Exception $e) {
        echo "⚠️ video_processing_queue.task_type 字段修复失败: " . $e->getMessage() . "<br>";
    }
    
    // 3. 修复 video_files 表的 status 字段
    echo "<h3>3. 修复 video_files 表的 status 字段</h3>";
    try {
        $db->query("
            ALTER TABLE `video_files` 
            MODIFY COLUMN `status` ENUM('pending', 'downloading', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT '处理状态'
        ");
        echo "✅ video_files.status 字段修复成功<br>";
    } catch (Exception $e) {
        echo "⚠️ video_files.status 字段修复失败: " . $e->getMessage() . "<br>";
    }
    
    // 4. 添加录制进度字段
    echo "<h3>4. 添加录制进度字段</h3>";
    try {
        $db->query("
            ALTER TABLE `video_files` 
            ADD COLUMN `recording_progress` TINYINT(3) DEFAULT 0 COMMENT '录制进度(0-100)' AFTER `status`
        ");
        echo "✅ recording_progress 字段添加成功<br>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️ recording_progress 字段已存在<br>";
        } else {
            echo "⚠️ recording_progress 字段添加失败: " . $e->getMessage() . "<br>";
        }
    }
    
    try {
        $db->query("
            ALTER TABLE `video_files` 
            ADD COLUMN `recording_started_at` TIMESTAMP NULL COMMENT '录制开始时间' AFTER `recording_progress`
        ");
        echo "✅ recording_started_at 字段添加成功<br>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️ recording_started_at 字段已存在<br>";
        } else {
            echo "⚠️ recording_started_at 字段添加失败: " . $e->getMessage() . "<br>";
        }
    }
    
    try {
        $db->query("
            ALTER TABLE `video_files` 
            ADD COLUMN `recording_completed_at` TIMESTAMP NULL COMMENT '录制完成时间' AFTER `recording_started_at`
        ");
        echo "✅ recording_completed_at 字段添加成功<br>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️ recording_completed_at 字段已存在<br>";
        } else {
            echo "⚠️ recording_completed_at 字段添加失败: " . $e->getMessage() . "<br>";
        }
    }
    
    try {
        $db->query("
            ALTER TABLE `video_files` 
            ADD COLUMN `recording_status` ENUM('pending', 'recording', 'completed', 'failed') DEFAULT 'pending' COMMENT '录制状态' AFTER `recording_completed_at`
        ");
        echo "✅ recording_status 字段添加成功<br>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️ recording_status 字段已存在<br>";
        } else {
            echo "⚠️ recording_status 字段添加失败: " . $e->getMessage() . "<br>";
        }
    }
    
    // 5. 创建录制进度日志表
    echo "<h3>5. 创建录制进度日志表</h3>";
    try {
        $db->query("
            CREATE TABLE IF NOT EXISTS `recording_progress_logs` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `video_file_id` INT(11) UNSIGNED NOT NULL COMMENT '视频文件ID',
                `progress` TINYINT(3) NOT NULL COMMENT '进度(0-100)',
                `message` VARCHAR(255) NOT NULL COMMENT '进度描述',
                `duration` INT(11) DEFAULT NULL COMMENT '已录制时长(秒)',
                `file_size` BIGINT(20) DEFAULT NULL COMMENT '当前文件大小(字节)',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
                
                FOREIGN KEY (`video_file_id`) REFERENCES `video_files`(`id`) ON DELETE CASCADE,
                INDEX `idx_video_file_id` (`video_file_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB COMMENT='录制进度日志表'
        ");
        echo "✅ recording_progress_logs 表创建成功<br>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "ℹ️ recording_progress_logs 表已存在<br>";
        } else {
            echo "⚠️ recording_progress_logs 表创建失败: " . $e->getMessage() . "<br>";
        }
    }
    
    // 6. 清理无效的任务记录
    echo "<h3>6. 清理无效的任务记录</h3>";
    try {
        $deleted = $db->query("
            DELETE FROM `video_processing_queue` 
            WHERE `status` NOT IN ('pending', 'processing', 'completed', 'failed', 'retry', 'record')
               OR `task_type` NOT IN ('record', 'download', 'transcode', 'segment', 'asr', 'analysis', 'report')
        ");
        echo "✅ 清理了 {$deleted} 条无效任务记录<br>";
    } catch (Exception $e) {
        echo "⚠️ 清理任务记录失败: " . $e->getMessage() . "<br>";
    }
    
    echo "<h3>修复完成！</h3>";
    echo "<p><a href='reset_video_tasks.php?order_id=19'>重置任务队列</a></p>";
    echo "<p><a href='admin/video_order_detail.php?id=19'>返回订单详情</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
