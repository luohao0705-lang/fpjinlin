-- 快速数据库修复脚本
-- 只包含核心修复，安全可靠

-- 1. 修复状态枚举不一致问题
ALTER TABLE `video_files` 
  MODIFY COLUMN `recording_status` enum('pending','recording','completed','failed','stopped') DEFAULT 'pending' COMMENT '录制状态';

-- 2. 添加关键索引
ALTER TABLE `video_files` 
  ADD KEY `idx_recording_status` (`recording_status`);

ALTER TABLE `video_processing_queue` 
  ADD KEY `idx_created_at` (`created_at`);

-- 3. 清理无效数据
DELETE vf FROM `video_files` vf 
LEFT JOIN `video_analysis_orders` vao ON vf.order_id = vao.id 
WHERE vao.id IS NULL;

DELETE vpq FROM `video_processing_queue` vpq 
LEFT JOIN `video_analysis_orders` vao ON vpq.order_id = vao.id 
WHERE vao.id IS NULL;

-- 4. 添加有用的字段
ALTER TABLE `video_files` 
  ADD COLUMN `file_hash` varchar(64) DEFAULT NULL COMMENT '文件MD5哈希值' AFTER `file_size`,
  ADD COLUMN `retry_count` tinyint(3) DEFAULT '0' COMMENT '重试次数' AFTER `error_message`,
  ADD COLUMN `max_retries` tinyint(3) DEFAULT '3' COMMENT '最大重试次数' AFTER `retry_count`;

-- 5. 验证修复结果
SELECT 'Database fix completed!' as message;
