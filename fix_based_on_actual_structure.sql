-- 基于实际表结构的修复脚本
-- 先检查再修复

-- 1. 检查并修复 video_processing_queue.status 字段
-- 如果当前字段不包含 'record'，则修改
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
       AND TABLE_NAME = 'video_processing_queue' 
       AND COLUMN_NAME = 'status' 
       AND COLUMN_TYPE LIKE '%record%') > 0,
    'SELECT "video_processing_queue.status 字段已包含 record" as message',
    'ALTER TABLE `video_processing_queue` MODIFY COLUMN `status` ENUM("pending", "processing", "completed", "failed", "retry", "record") DEFAULT "pending" COMMENT "状态"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. 检查并修复 video_processing_queue.task_type 字段
-- 如果当前字段不包含 'record'，则修改
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
       AND TABLE_NAME = 'video_processing_queue' 
       AND COLUMN_NAME = 'task_type' 
       AND COLUMN_TYPE LIKE '%record%') > 0,
    'SELECT "video_processing_queue.task_type 字段已包含 record" as message',
    'ALTER TABLE `video_processing_queue` MODIFY COLUMN `task_type` ENUM("record", "download", "transcode", "segment", "asr", "analysis", "report") NOT NULL COMMENT "任务类型"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. 检查并修复 video_files.status 字段
-- 如果当前字段不包含 'downloading'，则修改
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
       AND TABLE_NAME = 'video_files' 
       AND COLUMN_NAME = 'status' 
       AND COLUMN_TYPE LIKE '%downloading%') > 0,
    'SELECT "video_files.status 字段已包含 downloading" as message',
    'ALTER TABLE `video_files` MODIFY COLUMN `status` ENUM("pending", "downloading", "processing", "completed", "failed") DEFAULT "pending" COMMENT "处理状态"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. 检查并添加 recording_progress 字段
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

-- 5. 检查并添加 recording_status 字段
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
       AND TABLE_NAME = 'video_files' 
       AND COLUMN_NAME = 'recording_status') > 0,
    'SELECT "recording_status 字段已存在" as message',
    'ALTER TABLE `video_files` ADD COLUMN `recording_status` ENUM("pending", "recording", "completed", "failed") DEFAULT "pending" COMMENT "录制状态" AFTER `recording_progress`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. 清理无效的任务记录
DELETE FROM `video_processing_queue` 
WHERE `status` NOT IN ('pending', 'processing', 'completed', 'failed', 'retry', 'record')
   OR `task_type` NOT IN ('record', 'download', 'transcode', 'segment', 'asr', 'analysis', 'report');

-- 7. 显示修复后的字段信息
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
