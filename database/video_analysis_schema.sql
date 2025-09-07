-- 视频分析模块数据库表结构
-- 复盘精灵系统 - 视频驱动分析升级
-- 创建时间: 2024

-- 1. 视频分析订单表
CREATE TABLE `video_analysis_orders` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT '用户ID',
    `order_no` VARCHAR(32) NOT NULL UNIQUE COMMENT '订单号',
    `title` VARCHAR(100) NOT NULL COMMENT '分析标题',
    `cost_coins` INT(11) UNSIGNED NOT NULL DEFAULT 50 COMMENT '消耗精灵币数量',
    `status` ENUM('pending', 'reviewing', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT '状态',
    
    -- 视频链接信息
    `self_video_link` VARCHAR(500) NOT NULL COMMENT '本方视频分享链接',
    `competitor_video_links` JSON COMMENT '同行视频分享链接数组',
    `self_flv_url` VARCHAR(500) DEFAULT NULL COMMENT '本方视频FLV地址',
    `competitor_flv_urls` JSON COMMENT '同行视频FLV地址数组',
    
    -- 视频处理信息
    `video_duration` INT(11) DEFAULT NULL COMMENT '视频时长(秒)',
    `processed_duration` INT(11) DEFAULT NULL COMMENT '实际处理时长(秒)',
    `video_resolution` VARCHAR(20) DEFAULT NULL COMMENT '视频分辨率',
    `video_size` BIGINT(20) DEFAULT NULL COMMENT '视频文件大小(字节)',
    
    -- 分析结果
    `ai_report` LONGTEXT COMMENT 'AI生成的分析报告(JSON)',
    `report_score` INT(3) DEFAULT NULL COMMENT '综合评分(0-100)',
    `report_level` ENUM('excellent', 'good', 'average', 'poor', 'unqualified') DEFAULT NULL COMMENT '等级',
    `error_message` TEXT COMMENT '错误信息',
    
    -- 时间戳
    `reviewed_at` TIMESTAMP NULL COMMENT '审核通过时间',
    `processing_started_at` TIMESTAMP NULL COMMENT '开始处理时间',
    `completed_at` TIMESTAMP NULL COMMENT '完成时间',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_order_no` (`order_no`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB COMMENT='视频分析订单表';

-- 2. 视频文件管理表
CREATE TABLE `video_files` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT(11) UNSIGNED NOT NULL COMMENT '订单ID',
    `video_type` ENUM('self', 'competitor') NOT NULL COMMENT '视频类型',
    `video_index` TINYINT(3) DEFAULT 0 COMMENT '同行视频序号(0为本方)',
    `original_url` VARCHAR(500) NOT NULL COMMENT '原始分享链接',
    `flv_url` VARCHAR(500) DEFAULT NULL COMMENT 'FLV地址',
    `oss_key` VARCHAR(255) DEFAULT NULL COMMENT 'OSS存储键',
    `file_size` BIGINT(20) DEFAULT NULL COMMENT '文件大小(字节)',
    `duration` INT(11) DEFAULT NULL COMMENT '时长(秒)',
    `resolution` VARCHAR(20) DEFAULT NULL COMMENT '分辨率',
    `status` ENUM('pending', 'downloading', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT '处理状态',
    `error_message` TEXT COMMENT '错误信息',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    
    FOREIGN KEY (`order_id`) REFERENCES `video_analysis_orders`(`id`) ON DELETE CASCADE,
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_video_type` (`video_type`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB COMMENT='视频文件管理表';

-- 3. 视频切片表
CREATE TABLE `video_segments` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `video_file_id` INT(11) UNSIGNED NOT NULL COMMENT '视频文件ID',
    `segment_index` INT(11) NOT NULL COMMENT '切片序号',
    `start_time` INT(11) NOT NULL COMMENT '开始时间(秒)',
    `end_time` INT(11) NOT NULL COMMENT '结束时间(秒)',
    `duration` INT(11) NOT NULL COMMENT '切片时长(秒)',
    `oss_key` VARCHAR(255) NOT NULL COMMENT 'OSS存储键',
    `file_size` BIGINT(20) DEFAULT NULL COMMENT '文件大小(字节)',
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT '处理状态',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    
    FOREIGN KEY (`video_file_id`) REFERENCES `video_files`(`id`) ON DELETE CASCADE,
    INDEX `idx_video_file_id` (`video_file_id`),
    INDEX `idx_segment_index` (`segment_index`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB COMMENT='视频切片表';

-- 4. 语音识别结果表
CREATE TABLE `video_transcripts` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `segment_id` INT(11) UNSIGNED NOT NULL COMMENT '切片ID',
    `start_time` DECIMAL(10,3) NOT NULL COMMENT '开始时间(秒)',
    `end_time` DECIMAL(10,3) NOT NULL COMMENT '结束时间(秒)',
    `text` TEXT NOT NULL COMMENT '识别文本',
    `confidence` DECIMAL(5,4) DEFAULT NULL COMMENT '置信度',
    `speaker_id` VARCHAR(50) DEFAULT NULL COMMENT '说话人ID',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    
    FOREIGN KEY (`segment_id`) REFERENCES `video_segments`(`id`) ON DELETE CASCADE,
    INDEX `idx_segment_id` (`segment_id`),
    INDEX `idx_start_time` (`start_time`)
) ENGINE=InnoDB COMMENT='语音识别结果表';

-- 5. 视频理解结果表
CREATE TABLE `video_analysis_results` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `segment_id` INT(11) UNSIGNED NOT NULL COMMENT '切片ID',
    `analysis_type` ENUM('scene', 'action', 'emotion', 'object', 'text') NOT NULL COMMENT '分析类型',
    `result_data` JSON NOT NULL COMMENT '分析结果数据',
    `confidence` DECIMAL(5,4) DEFAULT NULL COMMENT '置信度',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    
    FOREIGN KEY (`segment_id`) REFERENCES `video_segments`(`id`) ON DELETE CASCADE,
    INDEX `idx_segment_id` (`segment_id`),
    INDEX `idx_analysis_type` (`analysis_type`)
) ENGINE=InnoDB COMMENT='视频理解结果表';

-- 6. 处理队列表
CREATE TABLE `video_processing_queue` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT(11) UNSIGNED NOT NULL COMMENT '订单ID',
    `task_type` ENUM('download', 'transcode', 'segment', 'asr', 'analysis', 'report') NOT NULL COMMENT '任务类型',
    `task_data` JSON NOT NULL COMMENT '任务数据',
    `priority` TINYINT(3) DEFAULT 5 COMMENT '优先级(1-10)',
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'retry') DEFAULT 'pending' COMMENT '状态',
    `retry_count` TINYINT(3) DEFAULT 0 COMMENT '重试次数',
    `max_retries` TINYINT(3) DEFAULT 3 COMMENT '最大重试次数',
    `error_message` TEXT COMMENT '错误信息',
    `started_at` TIMESTAMP NULL COMMENT '开始时间',
    `completed_at` TIMESTAMP NULL COMMENT '完成时间',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    
    FOREIGN KEY (`order_id`) REFERENCES `video_analysis_orders`(`id`) ON DELETE CASCADE,
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_task_type` (`task_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB COMMENT='视频处理队列表';

-- 7. 系统配置表扩展
INSERT INTO `system_configs` (`config_key`, `config_value`, `config_type`, `description`) VALUES
('video_analysis_cost_coins', '50', 'int', '视频分析消耗精灵币数量'),
('max_video_duration', '3600', 'int', '最大视频时长(秒)'),
('video_segment_duration', '120', 'int', '视频切片时长(秒)'),
('video_resolution', '720p', 'string', '视频转码分辨率'),
('video_bitrate', '1500k', 'string', '视频转码码率'),
('audio_bitrate', '64k', 'string', '音频转码码率'),
('oss_bucket', '', 'string', '阿里云OSS存储桶名称'),
('oss_endpoint', '', 'string', '阿里云OSS端点'),
('oss_access_key', '', 'string', '阿里云OSS访问密钥'),
('oss_secret_key', '', 'string', '阿里云OSS秘密密钥'),
('qwen_omni_api_key', '', 'string', '阿里云Qwen-Omni API密钥'),
('qwen_omni_api_url', 'https://dashscope.aliyuncs.com/api/v1/services/aigc/video-understanding/generation', 'string', 'Qwen-Omni API地址'),
('whisper_model_path', '/opt/whisper/models', 'string', 'Whisper模型路径'),
('max_concurrent_processing', '3', 'int', '最大并发处理数量'),
('video_retention_days', '30', 'int', '视频文件保留天数');

-- 8. 更新用户表，增加视频分析相关字段
ALTER TABLE `users` 
ADD COLUMN `total_video_orders` INT(11) UNSIGNED DEFAULT 0 COMMENT '总视频分析订单数' AFTER `total_orders`;

-- 9. 更新精灵币交易记录表，增加视频分析类型
ALTER TABLE `coin_transactions` 
MODIFY COLUMN `type` ENUM('recharge', 'consume', 'refund', 'video_analysis') NOT NULL COMMENT '交易类型';
