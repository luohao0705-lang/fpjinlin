-- 视频分析系统数据库修复命令
-- 解决录制任务无法启动的问题

-- 1. 修复 video_processing_queue 表的字段
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `status` ENUM('pending', 'processing', 'completed', 'failed', 'retry', 'record') DEFAULT 'pending' COMMENT '状态';

ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `task_type` ENUM('record', 'download', 'transcode', 'segment', 'asr', 'analysis', 'report') NOT NULL COMMENT '任务类型';

-- 2. 添加录制进度字段到 video_files 表
ALTER TABLE `video_files` 
ADD COLUMN `recording_progress` TINYINT(3) DEFAULT 0 COMMENT '录制进度(0-100)' AFTER `status`,
ADD COLUMN `recording_started_at` TIMESTAMP NULL COMMENT '录制开始时间' AFTER `recording_progress`,
ADD COLUMN `recording_completed_at` TIMESTAMP NULL COMMENT '录制完成时间' AFTER `recording_started_at`,
ADD COLUMN `recording_status` ENUM('pending', 'recording', 'completed', 'failed') DEFAULT 'pending' COMMENT '录制状态' AFTER `recording_completed_at`;

-- 3. 创建录制进度日志表
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

-- 4. 清理现有错误任务（可选）
-- DELETE FROM `video_processing_queue` WHERE `status` NOT IN ('pending', 'processing', 'completed', 'failed', 'retry', 'record');
