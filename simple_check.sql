-- 简单检查脚本
-- 直接查看表结构和数据

-- 1. 查看所有表
SHOW TABLES;

-- 2. 查看 video_processing_queue 表结构
SHOW CREATE TABLE `video_processing_queue`;

-- 3. 查看 video_files 表结构  
SHOW CREATE TABLE `video_files`;

-- 4. 查看 video_processing_queue 表的前几条数据
SELECT * FROM `video_processing_queue` LIMIT 5;

-- 5. 查看 video_files 表的前几条数据
SELECT * FROM `video_files` LIMIT 5;
