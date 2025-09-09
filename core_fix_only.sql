-- 核心修复脚本 - 只修复必要的字段
-- 解决 "Data truncated for column 'status'" 错误

-- 1. 修复 video_processing_queue 表的 status 字段
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `status` ENUM('pending', 'processing', 'completed', 'failed', 'retry', 'record') DEFAULT 'pending' COMMENT '状态';

-- 2. 修复 video_processing_queue 表的 task_type 字段  
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `task_type` ENUM('record', 'download', 'transcode', 'segment', 'asr', 'analysis', 'report') NOT NULL COMMENT '任务类型';

-- 3. 修复 video_files 表的 status 字段
ALTER TABLE `video_files` 
MODIFY COLUMN `status` ENUM('pending', 'downloading', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT '处理状态';

-- 4. 清理无效的任务记录
DELETE FROM `video_processing_queue` 
WHERE `status` NOT IN ('pending', 'processing', 'completed', 'failed', 'retry', 'record')
   OR `task_type` NOT IN ('record', 'download', 'transcode', 'segment', 'asr', 'analysis', 'report');
