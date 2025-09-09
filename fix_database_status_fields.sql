-- 修复数据库状态字段，添加stopped状态和recording相关字段
-- 兼容MySQL 5.7版本

-- 1. 添加stopped状态到video_analysis_orders表
ALTER TABLE `video_analysis_orders` 
MODIFY COLUMN `status` ENUM('pending', 'reviewing', 'processing', 'completed', 'failed', 'stopped') DEFAULT 'pending' COMMENT '状态';

-- 2. 添加stopped状态到video_files表
ALTER TABLE `video_files` 
MODIFY COLUMN `status` ENUM('pending', 'downloading', 'processing', 'completed', 'failed', 'stopped') DEFAULT 'pending' COMMENT '处理状态';

-- 3. 添加stopped状态到video_processing_queue表
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `status` ENUM('pending', 'processing', 'completed', 'failed', 'stopped') DEFAULT 'pending' COMMENT '处理状态';

-- 4. 添加recording相关字段到video_files表
-- 先检查字段是否存在，如果不存在则添加
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'video_files' 
     AND COLUMN_NAME = 'recording_progress') = 0,
    'ALTER TABLE `video_files` ADD COLUMN `recording_progress` TINYINT(3) DEFAULT 0 COMMENT ''录制进度(0-100)''',
    'SELECT ''recording_progress column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'video_files' 
     AND COLUMN_NAME = 'recording_status') = 0,
    'ALTER TABLE `video_files` ADD COLUMN `recording_status` ENUM(''pending'', ''recording'', ''completed'', ''failed'', ''stopped'') DEFAULT ''pending'' COMMENT ''录制状态''',
    'SELECT ''recording_status column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'video_files' 
     AND COLUMN_NAME = 'recording_started_at') = 0,
    'ALTER TABLE `video_files` ADD COLUMN `recording_started_at` TIMESTAMP NULL COMMENT ''录制开始时间''',
    'SELECT ''recording_started_at column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'video_files' 
     AND COLUMN_NAME = 'recording_completed_at') = 0,
    'ALTER TABLE `video_files` ADD COLUMN `recording_completed_at` TIMESTAMP NULL COMMENT ''录制完成时间''',
    'SELECT ''recording_completed_at column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. 添加retry_count字段到video_processing_queue表
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'video_processing_queue' 
     AND COLUMN_NAME = 'retry_count') = 0,
    'ALTER TABLE `video_processing_queue` ADD COLUMN `retry_count` TINYINT(3) DEFAULT 0 COMMENT ''重试次数''',
    'SELECT ''retry_count column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
