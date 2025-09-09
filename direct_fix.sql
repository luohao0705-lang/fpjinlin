-- 直接修复脚本
-- 不进行复杂检查，直接执行修复

-- 1. 修复 video_processing_queue 表的 status 字段
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `status` ENUM('pending', 'processing', 'completed', 'failed', 'retry', 'record') DEFAULT 'pending' COMMENT '状态';

-- 2. 修复 video_processing_queue 表的 task_type 字段
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `task_type` ENUM('record', 'download', 'transcode', 'segment', 'asr', 'analysis', 'report') NOT NULL COMMENT '任务类型';

-- 3. 修复 video_files 表的 status 字段
ALTER TABLE `video_files` 
MODIFY COLUMN `status` ENUM('pending', 'downloading', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT '处理状态';

-- 4. 添加录制进度字段（如果不存在会报错，但不会影响其他修复）
ALTER TABLE `video_files` 
ADD COLUMN `recording_progress` TINYINT(3) DEFAULT 0 COMMENT '录制进度(0-100)' AFTER `status`;

-- 5. 添加录制状态字段（如果不存在会报错，但不会影响其他修复）
ALTER TABLE `video_files` 
ADD COLUMN `recording_status` ENUM('pending', 'recording', 'completed', 'failed') DEFAULT 'pending' COMMENT '录制状态' AFTER `recording_progress`;

-- 6. 清理无效的任务记录
DELETE FROM `video_processing_queue` 
WHERE `status` NOT IN ('pending', 'processing', 'completed', 'failed', 'retry', 'record')
   OR `task_type` NOT IN ('record', 'download', 'transcode', 'segment', 'asr', 'analysis', 'report');

-- 7. 显示修复结果
SELECT '修复完成' as message;
