-- 添加缺失的file_path字段到video_files表
USE fupan_jingling;

-- 检查并添加file_path字段
ALTER TABLE video_files 
ADD COLUMN IF NOT EXISTS file_path VARCHAR(500) DEFAULT NULL COMMENT '文件路径' AFTER flv_url;

-- 检查并添加其他可能缺失的字段
ALTER TABLE video_files 
ADD COLUMN IF NOT EXISTS resolution VARCHAR(20) DEFAULT NULL COMMENT '视频分辨率' AFTER duration;

-- 显示更新后的表结构
DESCRIBE video_files;
