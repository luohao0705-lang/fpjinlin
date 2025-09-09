-- 清理待处理的视频数据
-- 请谨慎执行，建议先备份数据

-- 1. 查看当前待处理的视频数量
SELECT COUNT(*) as pending_videos FROM video_files WHERE status = 'pending';

-- 2. 查看待处理的视频详情
SELECT id, order_id, video_type, original_url, flv_url, status, created_at 
FROM video_files 
WHERE status = 'pending' 
ORDER BY created_at DESC 
LIMIT 10;

-- 3. 删除待处理的视频文件记录
DELETE FROM video_files WHERE status = 'pending';

-- 4. 删除相关的处理任务
DELETE FROM video_processing_queue WHERE status = 'pending';

-- 5. 查看清理后的结果
SELECT COUNT(*) as remaining_videos FROM video_files;
SELECT COUNT(*) as remaining_tasks FROM video_processing_queue;
