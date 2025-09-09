-- 简化版数据库修复脚本，兼容MySQL 5.7
-- 分步执行，避免语法错误

-- 步骤1: 添加stopped状态到video_analysis_orders表
ALTER TABLE `video_analysis_orders` 
MODIFY COLUMN `status` ENUM('pending', 'reviewing', 'processing', 'completed', 'failed', 'stopped') DEFAULT 'pending' COMMENT '状态';

-- 步骤2: 添加stopped状态到video_files表
ALTER TABLE `video_files` 
MODIFY COLUMN `status` ENUM('pending', 'downloading', 'processing', 'completed', 'failed', 'stopped') DEFAULT 'pending' COMMENT '处理状态';

-- 步骤3: 添加stopped状态到video_processing_queue表
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `status` ENUM('pending', 'processing', 'completed', 'failed', 'stopped') DEFAULT 'pending' COMMENT '处理状态';
