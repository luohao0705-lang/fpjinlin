-- 清理特定订单的待处理视频
-- 根据你的截图，order_id = 12 有75个视频

-- 查看order_id = 12的待处理视频
SELECT id, video_type, original_url, flv_url, status, created_at 
FROM video_files 
WHERE order_id = 12 AND status = 'pending';

-- 删除order_id = 12的待处理视频
DELETE FROM video_files WHERE order_id = 12 AND status = 'pending';

-- 删除order_id = 12的待处理任务
DELETE FROM video_processing_queue WHERE order_id = 12 AND status = 'pending';

-- 查看清理结果
SELECT COUNT(*) as remaining_videos_in_order_12 FROM video_files WHERE order_id = 12;
