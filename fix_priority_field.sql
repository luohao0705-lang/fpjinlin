-- 修复priority字段类型，支持更大的数值范围
-- 复盘精灵系统 - 数据库修复

-- 修改video_processing_queue表的priority字段类型
ALTER TABLE `video_processing_queue` 
MODIFY COLUMN `priority` INT(11) DEFAULT 5 COMMENT '优先级(1-1000)';

-- 验证修改结果
DESCRIBE `video_processing_queue`;
