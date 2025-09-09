-- 添加recording相关字段到video_files表
-- 如果字段已存在会报错，可以忽略

-- 添加录制进度字段
ALTER TABLE `video_files` 
ADD COLUMN `recording_progress` TINYINT(3) DEFAULT 0 COMMENT '录制进度(0-100)';

-- 添加录制状态字段
ALTER TABLE `video_files` 
ADD COLUMN `recording_status` ENUM('pending', 'recording', 'completed', 'failed', 'stopped') DEFAULT 'pending' COMMENT '录制状态';

-- 添加录制开始时间字段
ALTER TABLE `video_files` 
ADD COLUMN `recording_started_at` TIMESTAMP NULL COMMENT '录制开始时间';

-- 添加录制完成时间字段
ALTER TABLE `video_files` 
ADD COLUMN `recording_completed_at` TIMESTAMP NULL COMMENT '录制完成时间';

-- 添加重试次数字段到video_processing_queue表
ALTER TABLE `video_processing_queue` 
ADD COLUMN `retry_count` TINYINT(3) DEFAULT 0 COMMENT '重试次数';
