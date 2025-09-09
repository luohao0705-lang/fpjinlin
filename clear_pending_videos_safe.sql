-- 安全清理待处理视频数据
-- 分步执行，避免误删

-- 步骤1: 查看待处理视频数量
SELECT COUNT(*) as pending_videos FROM video_files WHERE status = 'pending';

-- 步骤2: 查看待处理视频的订单ID
SELECT DISTINCT order_id, COUNT(*) as video_count 
FROM video_files 
WHERE status = 'pending' 
GROUP BY order_id;

-- 步骤3: 查看待处理的任务数量
SELECT COUNT(*) as pending_tasks FROM video_processing_queue WHERE status = 'pending';

-- 步骤4: 如果确认要删除，取消下面的注释并执行
-- DELETE FROM video_files WHERE status = 'pending';
-- DELETE FROM video_processing_queue WHERE status = 'pending';

-- 步骤5: 查看清理后的结果
-- SELECT COUNT(*) as remaining_videos FROM video_files;
-- SELECT COUNT(*) as remaining_tasks FROM video_processing_queue;
