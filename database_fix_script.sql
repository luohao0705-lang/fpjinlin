-- 数据库修复脚本
-- 修复复盘精灵系统的数据库问题
-- 创建时间: 2025-01-27

-- =============================================
-- 1. 修复外键约束问题
-- =============================================

-- 删除现有的外键约束
ALTER TABLE `video_files` DROP FOREIGN KEY `video_files_ibfk_1`;
ALTER TABLE `video_processing_queue` DROP FOREIGN KEY `video_processing_queue_ibfk_1`;
ALTER TABLE `video_segments` DROP FOREIGN KEY `video_segments_ibfk_1`;
ALTER TABLE `video_transcripts` DROP FOREIGN KEY `video_transcripts_ibfk_1`;
ALTER TABLE `video_analysis_results` DROP FOREIGN KEY `video_analysis_results_ibfk_1`;
ALTER TABLE `recording_progress_logs` DROP FOREIGN KEY `recording_progress_logs_ibfk_1`;

-- 重新添加正确的外键约束
ALTER TABLE `video_files` 
  ADD CONSTRAINT `video_files_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `video_analysis_orders` (`id`) ON DELETE CASCADE;

ALTER TABLE `video_processing_queue` 
  ADD CONSTRAINT `video_processing_queue_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `video_analysis_orders` (`id`) ON DELETE CASCADE;

ALTER TABLE `video_segments` 
  ADD CONSTRAINT `video_segments_ibfk_1` FOREIGN KEY (`video_file_id`) REFERENCES `video_files` (`id`) ON DELETE CASCADE;

ALTER TABLE `video_transcripts` 
  ADD CONSTRAINT `video_transcripts_ibfk_1` FOREIGN KEY (`segment_id`) REFERENCES `video_segments` (`id`) ON DELETE CASCADE;

ALTER TABLE `video_analysis_results` 
  ADD CONSTRAINT `video_analysis_results_ibfk_1` FOREIGN KEY (`segment_id`) REFERENCES `video_segments` (`id`) ON DELETE CASCADE;

ALTER TABLE `recording_progress_logs` 
  ADD CONSTRAINT `recording_progress_logs_ibfk_1` FOREIGN KEY (`video_file_id`) REFERENCES `video_files` (`id`) ON DELETE CASCADE;

-- =============================================
-- 2. 统一状态枚举值
-- =============================================

-- 修复 video_files 表的 recording_status 字段
ALTER TABLE `video_files` 
  MODIFY COLUMN `recording_status` enum('pending','recording','completed','failed','stopped') DEFAULT 'pending' COMMENT '录制状态';

-- 确保 video_analysis_orders 表的状态枚举包含所有必要状态
ALTER TABLE `video_analysis_orders` 
  MODIFY COLUMN `status` enum('pending','reviewing','processing','completed','failed','stopped') DEFAULT 'pending' COMMENT '状态';

-- 确保 video_files 表的状态枚举包含所有必要状态
ALTER TABLE `video_files` 
  MODIFY COLUMN `status` enum('pending','downloading','processing','completed','failed','stopped') DEFAULT 'pending' COMMENT '处理状态';

-- 确保 video_processing_queue 表的状态枚举包含所有必要状态
ALTER TABLE `video_processing_queue` 
  MODIFY COLUMN `status` enum('pending','processing','completed','failed','stopped') DEFAULT 'pending' COMMENT '处理状态';

-- 确保 video_segments 表的状态枚举包含所有必要状态
ALTER TABLE `video_segments` 
  MODIFY COLUMN `status` enum('pending','processing','completed','failed') DEFAULT 'pending' COMMENT '处理状态';

-- =============================================
-- 3. 添加缺失的索引
-- =============================================

-- 为 video_files 表添加 recording_status 索引
ALTER TABLE `video_files` 
  ADD KEY `idx_recording_status` (`recording_status`);

-- 为 video_processing_queue 表添加 created_at 索引
ALTER TABLE `video_processing_queue` 
  ADD KEY `idx_created_at` (`created_at`);

-- 为 video_files 表添加 recording_started_at 索引
ALTER TABLE `video_files` 
  ADD KEY `idx_recording_started_at` (`recording_started_at`);

-- 为 video_files 表添加 recording_completed_at 索引
ALTER TABLE `video_files` 
  ADD KEY `idx_recording_completed_at` (`recording_completed_at`);

-- 为 video_analysis_orders 表添加 reviewed_at 索引
ALTER TABLE `video_analysis_orders` 
  ADD KEY `idx_reviewed_at` (`reviewed_at`);

-- 为 video_analysis_orders 表添加 processing_started_at 索引
ALTER TABLE `video_analysis_orders` 
  ADD KEY `idx_processing_started_at` (`processing_started_at`);

-- 为 video_analysis_orders 表添加 completed_at 索引
ALTER TABLE `video_analysis_orders` 
  ADD KEY `idx_completed_at` (`completed_at`);

-- =============================================
-- 4. 修复数据一致性问题
-- =============================================

-- 清理无效的 video_files 记录（没有对应订单的）
DELETE vf FROM `video_files` vf 
LEFT JOIN `video_analysis_orders` vao ON vf.order_id = vao.id 
WHERE vao.id IS NULL;

-- 清理无效的 video_processing_queue 记录（没有对应订单的）
DELETE vpq FROM `video_processing_queue` vpq 
LEFT JOIN `video_analysis_orders` vao ON vpq.order_id = vao.id 
WHERE vao.id IS NULL;

-- 清理无效的 recording_progress_logs 记录（没有对应视频文件的）
DELETE rpl FROM `recording_progress_logs` rpl 
LEFT JOIN `video_files` vf ON rpl.video_file_id = vf.id 
WHERE vf.id IS NULL;

-- =============================================
-- 5. 添加缺失的字段（如果需要）
-- =============================================

-- 为 video_files 表添加 file_hash 字段（用于文件完整性检查）
ALTER TABLE `video_files` 
  ADD COLUMN `file_hash` varchar(64) DEFAULT NULL COMMENT '文件MD5哈希值' AFTER `file_size`;

-- 为 video_files 表添加 retry_count 字段
ALTER TABLE `video_files` 
  ADD COLUMN `retry_count` tinyint(3) DEFAULT '0' COMMENT '重试次数' AFTER `error_message`;

-- 为 video_files 表添加 max_retries 字段
ALTER TABLE `video_files` 
  ADD COLUMN `max_retries` tinyint(3) DEFAULT '3' COMMENT '最大重试次数' AFTER `retry_count`;

-- =============================================
-- 6. 优化表结构
-- =============================================

-- 为 video_analysis_orders 表添加 processing_priority 字段
ALTER TABLE `video_analysis_orders` 
  ADD COLUMN `processing_priority` int(11) DEFAULT '5' COMMENT '处理优先级(1-1000)' AFTER `cost_coins`;

-- 为 video_analysis_orders 表添加 processing_notes 字段
ALTER TABLE `video_analysis_orders` 
  ADD COLUMN `processing_notes` text COMMENT '处理备注' AFTER `error_message`;

-- 为 video_files 表添加 processing_priority 字段
ALTER TABLE `video_files` 
  ADD COLUMN `processing_priority` int(11) DEFAULT '5' COMMENT '处理优先级(1-1000)' AFTER `video_index`;

-- =============================================
-- 7. 创建视图（用于简化查询）
-- =============================================

-- 创建视频分析订单详情视图
CREATE OR REPLACE VIEW `video_analysis_order_details` AS
SELECT 
    vao.id,
    vao.user_id,
    vao.order_no,
    vao.title,
    vao.cost_coins,
    vao.status,
    vao.self_video_link,
    vao.competitor_video_links,
    vao.self_flv_url,
    vao.competitor_flv_urls,
    vao.video_duration,
    vao.processed_duration,
    vao.video_resolution,
    vao.video_size,
    vao.report_score,
    vao.report_level,
    vao.error_message,
    vao.reviewed_at,
    vao.processing_started_at,
    vao.completed_at,
    vao.created_at,
    vao.updated_at,
    u.phone,
    u.nickname,
    COUNT(vf.id) as video_file_count,
    COUNT(CASE WHEN vf.status = 'completed' THEN 1 END) as completed_video_count,
    COUNT(CASE WHEN vf.status = 'failed' THEN 1 END) as failed_video_count
FROM `video_analysis_orders` vao
LEFT JOIN `users` u ON vao.user_id = u.id
LEFT JOIN `video_files` vf ON vao.id = vf.order_id
GROUP BY vao.id;

-- 创建视频文件处理状态视图
CREATE OR REPLACE VIEW `video_file_processing_status` AS
SELECT 
    vf.id,
    vf.order_id,
    vf.video_type,
    vf.video_index,
    vf.original_url,
    vf.flv_url,
    vf.file_path,
    vf.file_size,
    vf.duration,
    vf.resolution,
    vf.status,
    vf.recording_progress,
    vf.recording_status,
    vf.recording_started_at,
    vf.recording_completed_at,
    vf.error_message,
    vf.retry_count,
    vf.max_retries,
    vao.order_no,
    vao.title,
    u.phone,
    u.nickname
FROM `video_files` vf
LEFT JOIN `video_analysis_orders` vao ON vf.order_id = vao.id
LEFT JOIN `users` u ON vao.user_id = u.id;

-- =============================================
-- 8. 创建存储过程（用于常用操作）
-- =============================================

DELIMITER //

-- 创建更新视频文件状态的存储过程
CREATE PROCEDURE `UpdateVideoFileStatus`(
    IN p_video_file_id INT,
    IN p_status VARCHAR(20),
    IN p_recording_progress TINYINT,
    IN p_error_message TEXT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    UPDATE `video_files` 
    SET 
        `status` = p_status,
        `recording_progress` = COALESCE(p_recording_progress, `recording_progress`),
        `error_message` = COALESCE(p_error_message, `error_message`),
        `updated_at` = CURRENT_TIMESTAMP
    WHERE `id` = p_video_file_id;
    
    -- 记录进度日志
    IF p_recording_progress IS NOT NULL THEN
        INSERT INTO `recording_progress_logs` 
        (`video_file_id`, `progress`, `message`, `created_at`)
        VALUES 
        (p_video_file_id, p_recording_progress, 
         CONCAT('状态更新为: ', p_status), CURRENT_TIMESTAMP);
    END IF;
    
    COMMIT;
END //

-- 创建清理过期数据的存储过程
CREATE PROCEDURE `CleanupExpiredData`(
    IN p_retention_days INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- 清理过期的错误日志
    DELETE FROM `error_logs` 
    WHERE `created_at` < DATE_SUB(NOW(), INTERVAL p_retention_days DAY);
    
    -- 清理过期的短信验证码
    DELETE FROM `sms_codes` 
    WHERE `expires_at` < NOW();
    
    -- 清理过期的录制进度日志
    DELETE FROM `recording_progress_logs` 
    WHERE `created_at` < DATE_SUB(NOW(), INTERVAL p_retention_days DAY);
    
    COMMIT;
END //

DELIMITER ;

-- =============================================
-- 9. 创建触发器（用于自动更新）
-- =============================================

-- 创建更新 video_analysis_orders 处理状态的触发器
DELIMITER //

CREATE TRIGGER `update_video_analysis_order_status` 
AFTER UPDATE ON `video_files`
FOR EACH ROW
BEGIN
    DECLARE total_files INT;
    DECLARE completed_files INT;
    DECLARE failed_files INT;
    DECLARE processing_files INT;
    
    -- 获取该订单的所有视频文件统计
    SELECT 
        COUNT(*),
        COUNT(CASE WHEN status = 'completed' THEN 1 END),
        COUNT(CASE WHEN status = 'failed' THEN 1 END),
        COUNT(CASE WHEN status IN ('pending', 'downloading', 'processing', 'recording') THEN 1 END)
    INTO total_files, completed_files, failed_files, processing_files
    FROM `video_files` 
    WHERE `order_id` = NEW.order_id;
    
    -- 根据文件状态更新订单状态
    IF completed_files = total_files THEN
        UPDATE `video_analysis_orders` 
        SET `status` = 'completed', `completed_at` = CURRENT_TIMESTAMP
        WHERE `id` = NEW.order_id AND `status` != 'completed';
    ELSEIF failed_files > 0 AND (completed_files + failed_files) = total_files THEN
        UPDATE `video_analysis_orders` 
        SET `status` = 'failed', `error_message` = '部分视频文件处理失败'
        WHERE `id` = NEW.order_id AND `status` != 'failed';
    ELSEIF processing_files > 0 THEN
        UPDATE `video_analysis_orders` 
        SET `status` = 'processing', `processing_started_at` = CURRENT_TIMESTAMP
        WHERE `id` = NEW.order_id AND `status` = 'pending';
    END IF;
END //

DELIMITER ;

-- =============================================
-- 10. 数据验证和修复
-- =============================================

-- 验证外键约束
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE REFERENCED_TABLE_SCHEMA = 'fupan_jingling' 
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- 验证索引
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'fupan_jingling' 
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- 验证数据一致性
SELECT 
    'video_analysis_orders' as table_name,
    COUNT(*) as record_count
FROM `video_analysis_orders`
UNION ALL
SELECT 
    'video_files' as table_name,
    COUNT(*) as record_count
FROM `video_files`
UNION ALL
SELECT 
    'video_processing_queue' as table_name,
    COUNT(*) as record_count
FROM `video_processing_queue`;

-- =============================================
-- 修复完成
-- =============================================

-- 显示修复结果
SELECT 'Database fix completed successfully!' as message;
