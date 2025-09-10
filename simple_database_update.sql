-- 简化的数据库更新脚本
-- 只包含核心字段更新

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
    'failed',
    'stopped'
) DEFAULT 'pending' COMMENT '订单状态';

-- 2. 为 video_analysis_orders 表添加核心字段
ALTER TABLE `video_analysis_orders` 
ADD COLUMN `current_stage` varchar(50) DEFAULT 'pending' COMMENT '当前处理阶段',
ADD COLUMN `stage_progress` tinyint(3) DEFAULT '0' COMMENT '阶段进度(0-100)',
ADD COLUMN `stage_message` varchar(255) DEFAULT NULL COMMENT '阶段描述信息';

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
    'failed',
    'stopped'
) DEFAULT 'pending' COMMENT '处理状态';

-- 4. 为 video_files 表添加核心字段
ALTER TABLE `video_files` 
ADD COLUMN `video_analysis_result` json DEFAULT NULL COMMENT '视频分析结果',
ADD COLUMN `speech_transcript` text DEFAULT NULL COMMENT '语音转录文本',
ADD COLUMN `speech_analysis_result` json DEFAULT NULL COMMENT '语音分析结果',
ADD COLUMN `processing_stage` varchar(50) DEFAULT 'pending' COMMENT '当前处理阶段',
ADD COLUMN `stage_progress` tinyint(3) DEFAULT '0' COMMENT '阶段进度(0-100)';

-- 5. 创建工作流进度日志表
CREATE TABLE IF NOT EXISTS `workflow_progress_logs` (
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
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工作流进度日志表';

-- 6. 添加索引
ALTER TABLE `video_analysis_orders` 
ADD KEY `idx_current_stage` (`current_stage`),
ADD KEY `idx_stage_progress` (`stage_progress`);

ALTER TABLE `video_files` 
ADD KEY `idx_processing_stage` (`processing_stage`),
ADD KEY `idx_stage_progress` (`stage_progress`);

-- 7. 验证更新
SELECT 'Database update completed successfully!' as message;
