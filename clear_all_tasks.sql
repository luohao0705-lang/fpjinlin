-- 清除所有任务相关数据库的SQL脚本
-- 危险操作，请谨慎使用！

-- 1. 清除视频处理队列
TRUNCATE TABLE video_processing_queue;

-- 2. 清除视频文件记录
TRUNCATE TABLE video_files;

-- 3. 清除视频分析订单
TRUNCATE TABLE video_analysis_orders;

-- 4. 清除操作日志
TRUNCATE TABLE operation_logs;

-- 5. 重置自增ID
ALTER TABLE video_processing_queue AUTO_INCREMENT = 1;
ALTER TABLE video_files AUTO_INCREMENT = 1;
ALTER TABLE video_analysis_orders AUTO_INCREMENT = 1;
ALTER TABLE operation_logs AUTO_INCREMENT = 1;

-- 6. 显示清理结果
SELECT 'video_processing_queue' as table_name, COUNT(*) as count FROM video_processing_queue
UNION ALL
SELECT 'video_files' as table_name, COUNT(*) as count FROM video_files
UNION ALL
SELECT 'video_analysis_orders' as table_name, COUNT(*) as count FROM video_analysis_orders
UNION ALL
SELECT 'operation_logs' as table_name, COUNT(*) as count FROM operation_logs;
