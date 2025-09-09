-- 修复video_files表的问题
-- 分步执行，避免错误

-- 步骤1: 先查看当前表结构
-- DESCRIBE `video_files`;

-- 步骤2: 如果status字段修改失败，尝试先删除再添加
-- 注意：这会丢失现有数据，请先备份！

-- 备份现有数据（可选）
-- CREATE TABLE `video_files_backup` AS SELECT * FROM `video_files`;

-- 方法1: 直接修改status字段（推荐）
ALTER TABLE `video_files` 
MODIFY COLUMN `status` ENUM('pending', 'downloading', 'processing', 'completed', 'failed', 'stopped') DEFAULT 'pending' COMMENT '处理状态';

-- 如果上面失败，尝试方法2: 先删除再添加
-- ALTER TABLE `video_files` DROP COLUMN `status`;
-- ALTER TABLE `video_files` ADD COLUMN `status` ENUM('pending', 'downloading', 'processing', 'completed', 'failed', 'stopped') DEFAULT 'pending' COMMENT '处理状态' AFTER `resolution`;
