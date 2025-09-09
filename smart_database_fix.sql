-- 智能数据库修复脚本
-- 只修复必要的字段，避免重复添加

-- 1. 修复 video_processing_queue 表的 status 字段
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `status` ENUM('pending', 'processing', 'completed', 'failed', 'retry', 'record') DEFAULT 'pending' COMMENT '状态';

-- 2. 修复 video_processing_queue 表的 task_type 字段
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `task_type` ENUM('record', 'download', 'transcode', 'segment', 'asr', 'analysis', 'report') NOT NULL COMMENT '任务类型';

-- 3. 修复 video_files 表的 status 字段
ALTER TABLE `video_files` 
MODIFY COLUMN `status` ENUM('pending', 'downloading', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT '处理状态';

-- 4. 检查并添加录制进度字段（如果不存在）
-- 注意：这些字段可能已经存在，如果存在会报错，但不会影响其他修复

-- 检查 recording_progress 字段是否存在
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
       AND TABLE_NAME = 'video_files' 
       AND COLUMN_NAME = 'recording_progress') > 0,
    'SELECT "recording_progress 字段已存在" as message',
    'ALTER TABLE `video_files` ADD COLUMN `recording_progress` TINYINT(3) DEFAULT 0 COMMENT "录制进度(0-100)" AFTER `status`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查 recording_started_at 字段是否存在
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
       AND TABLE_NAME = 'video_files' 
       AND COLUMN_NAME = 'recording_started_at') > 0,
    'SELECT "recording_started_at 字段已存在" as message',
    'ALTER TABLE `video_files` ADD COLUMN `recording_started_at` TIMESTAMP NULL COMMENT "录制开始时间" AFTER `recording_progress`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查 recording_completed_at 字段是否存在
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
       AND TABLE_NAME = 'video_files' 
       AND COLUMN_NAME = 'recording_completed_at') > 0,
    'SELECT "recording_completed_at 字段已存在" as message',
    'ALTER TABLE `video_files` ADD COLUMN `recording_completed_at` TIMESTAMP NULL COMMENT "录制完成时间" AFTER `recording_started_at`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查 recording_status 字段是否存在
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
       AND TABLE_NAME = 'video_files' 
       AND COLUMN_NAME = 'recording_status') > 0,
    'SELECT "recording_status 字段已存在" as message',
    'ALTER TABLE `video_files` ADD COLUMN `recording_status` ENUM("pending", "recording", "completed", "failed") DEFAULT "pending" COMMENT "录制状态" AFTER `recording_completed_at`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. 创建录制进度日志表（如果不存在）
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
) ENGINE=InnoDB COMMENT='录制进度日志表';

-- 6. 清理无效的任务记录
DELETE FROM `video_processing_queue` 
WHERE `status` NOT IN ('pending', 'processing', 'completed', 'failed', 'retry', 'record')
   OR `task_type` NOT IN ('record', 'download', 'transcode', 'segment', 'asr', 'analysis', 'report');

-- 7. 显示修复结果
SELECT 
    'video_processing_queue' as table_name,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'video_processing_queue' 
  AND COLUMN_NAME IN ('status', 'task_type')

UNION ALL

SELECT 
    'video_files' as table_name,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'video_files' 
  AND COLUMN_NAME IN ('status', 'recording_progress', 'recording_status');
