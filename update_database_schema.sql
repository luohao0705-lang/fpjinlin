-- 更新数据库表结构以支持新的工作流程
-- 执行前请先备份数据库

-- 1. 更新 video_analysis_orders 表状态枚举
ALTER TABLE `video_analysis_orders` 
MODIFY COLUMN `status` enum(
    'pending',
    'recording',
    'recording_completed', 
    'transcoding',
    'transcoding_completed',
    'ai_analyzing',
    'ai_analysis_completed',
    'speech_extracting',
    'speech_extraction_completed',
    'script_analyzing',
    'script_analysis_completed',
    'report_generating',
    'completed',
    'failed'
) DEFAULT 'pending' COMMENT '订单状态';

-- 2. 为 video_analysis_orders 表添加新字段
ALTER TABLE `video_analysis_orders` 
ADD COLUMN `current_stage` varchar(50) DEFAULT 'pending' COMMENT '当前处理阶段',
ADD COLUMN `stage_progress` tinyint(3) DEFAULT '0' COMMENT '阶段进度(0-100)',
ADD COLUMN `stage_message` varchar(255) DEFAULT NULL COMMENT '阶段描述信息',
ADD COLUMN `recording_started_at` timestamp NULL DEFAULT NULL COMMENT '录制开始时间',
ADD COLUMN `recording_completed_at` timestamp NULL DEFAULT NULL COMMENT '录制完成时间',
ADD COLUMN `transcoding_started_at` timestamp NULL DEFAULT NULL COMMENT '转码开始时间',
ADD COLUMN `transcoding_completed_at` timestamp NULL DEFAULT NULL COMMENT '转码完成时间',
ADD COLUMN `ai_analysis_started_at` timestamp NULL DEFAULT NULL COMMENT 'AI分析开始时间',
ADD COLUMN `ai_analysis_completed_at` timestamp NULL DEFAULT NULL COMMENT 'AI分析完成时间',
ADD COLUMN `speech_extraction_started_at` timestamp NULL DEFAULT NULL COMMENT '语音提取开始时间',
ADD COLUMN `speech_extraction_completed_at` timestamp NULL DEFAULT NULL COMMENT '语音提取完成时间',
ADD COLUMN `script_analysis_started_at` timestamp NULL DEFAULT NULL COMMENT '话术分析开始时间',
ADD COLUMN `script_analysis_completed_at` timestamp NULL DEFAULT NULL COMMENT '话术分析完成时间',
ADD COLUMN `report_generation_started_at` timestamp NULL DEFAULT NULL COMMENT '报告生成开始时间',
ADD COLUMN `report_generation_completed_at` timestamp NULL DEFAULT NULL COMMENT '报告生成完成时间';

-- 3. 更新 video_files 表状态枚举
ALTER TABLE `video_files` 
MODIFY COLUMN `status` enum(
    'pending',
    'recording',
    'recording_completed',
    'transcoding', 
    'transcoding_completed',
    'ai_analyzing',
    'ai_analysis_completed',
    'speech_extracting',
    'speech_extraction_completed',
    'completed',
    'failed'
) DEFAULT 'pending' COMMENT '处理状态';

-- 4. 为 video_files 表添加新字段
ALTER TABLE `video_files` 
ADD COLUMN `video_analysis_result` json DEFAULT NULL COMMENT '视频分析结果',
ADD COLUMN `speech_transcript` text DEFAULT NULL COMMENT '语音转录文本',
ADD COLUMN `speech_analysis_result` json DEFAULT NULL COMMENT '语音分析结果',
ADD COLUMN `processing_stage` varchar(50) DEFAULT 'pending' COMMENT '当前处理阶段',
ADD COLUMN `stage_progress` tinyint(3) DEFAULT '0' COMMENT '阶段进度(0-100)';

-- 5. 更新 video_processing_queue 表任务类型
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `task_type` enum(
    'record',
    'transcode', 
    'segment',
    'ai_analyze',
    'speech_extract',
    'script_analyze',
    'generate_report'
) NOT NULL COMMENT '任务类型';

-- 6. 创建视频分析结果详情表
CREATE TABLE `video_analysis_details` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` int(11) UNSIGNED NOT NULL COMMENT '订单ID',
    `video_file_id` int(11) UNSIGNED NOT NULL COMMENT '视频文件ID',
    `analysis_type` enum('self','competitor1','competitor2') NOT NULL COMMENT '分析类型',
    `video_analysis_data` json NOT NULL COMMENT '视频分析数据',
    `speech_transcript` text COMMENT '语音转录文本',
    `speech_analysis_data` json COMMENT '语音分析数据',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_video_file_id` (`video_file_id`),
    KEY `idx_analysis_type` (`analysis_type`),
    CONSTRAINT `video_analysis_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `video_analysis_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `video_analysis_details_ibfk_2` FOREIGN KEY (`video_file_id`) REFERENCES `video_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频分析结果详情表';

-- 7. 创建工作流进度日志表
CREATE TABLE `workflow_progress_logs` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` int(11) UNSIGNED NOT NULL COMMENT '订单ID',
    `stage` varchar(50) NOT NULL COMMENT '处理阶段',
    `progress` tinyint(3) NOT NULL COMMENT '进度(0-100)',
    `message` varchar(255) NOT NULL COMMENT '进度描述',
    `video_file_id` int(11) UNSIGNED DEFAULT NULL COMMENT '关联视频文件ID',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_stage` (`stage`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `workflow_progress_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `video_analysis_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `workflow_progress_logs_ibfk_2` FOREIGN KEY (`video_file_id`) REFERENCES `video_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工作流进度日志表';

-- 8. 添加索引优化查询性能
ALTER TABLE `video_analysis_orders` 
ADD KEY `idx_current_stage` (`current_stage`),
ADD KEY `idx_stage_progress` (`stage_progress`);

ALTER TABLE `video_files` 
ADD KEY `idx_processing_stage` (`processing_stage`),
ADD KEY `idx_stage_progress` (`stage_progress`);

-- 9. 更新系统配置
INSERT INTO `system_configs` (`config_key`, `config_value`, `config_type`, `description`) VALUES
('recording_duration', '60', 'number', '录制时长(秒)'),
('segment_duration', '20', 'number', '切片时长(秒)'),
('max_concurrent_recordings', '3', 'number', '最大并发录制数量'),
('max_concurrent_transcoding', '3', 'number', '最大并发转码数量'),
('max_concurrent_ai_analysis', '3', 'number', '最大并发AI分析数量'),
('workflow_timeout_minutes', '30', 'number', '工作流超时时间(分钟)')
ON DUPLICATE KEY UPDATE 
`config_value` = VALUES(`config_value`),
`updated_at` = CURRENT_TIMESTAMP;

-- 10. 验证更新结果
SELECT 'Database schema updated successfully!' as message;
