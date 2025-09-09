-- 检查表结构
-- 查看当前字段定义

-- 1. 检查 video_processing_queue 表结构
DESCRIBE `video_processing_queue`;

-- 2. 检查 video_files 表结构  
DESCRIBE `video_files`;

-- 3. 检查 recording_progress_logs 表结构
DESCRIBE `recording_progress_logs`;

-- 4. 查看 video_processing_queue 表的 status 字段定义
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'video_processing_queue' 
  AND COLUMN_NAME = 'status';

-- 5. 查看 video_processing_queue 表的 task_type 字段定义
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'video_processing_queue' 
  AND COLUMN_NAME = 'task_type';

-- 6. 查看 video_files 表的 status 字段定义
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'video_files' 
  AND COLUMN_NAME = 'status';
