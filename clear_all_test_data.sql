-- 清理所有测试数据
-- 包括待处理的视频和任务

-- 1. 删除所有待处理的视频文件
DELETE FROM video_files WHERE status = 'pending';

-- 2. 删除所有待处理的任务
DELETE FROM video_processing_queue WHERE status = 'pending';

-- 3. 删除所有处理中的任务（如果有的话）
DELETE FROM video_processing_queue WHERE status = 'processing';

-- 4. 删除所有失败的任务（如果有的话）
DELETE FROM video_processing_queue WHERE status = 'failed';

-- 5. 重置所有视频文件状态为pending（保留数据但重置状态）
UPDATE video_files SET status = 'pending' WHERE status IN ('processing', 'completed', 'failed', 'stopped');

-- 6. 查看清理结果
SELECT 
    (SELECT COUNT(*) FROM video_files) as total_videos,
    (SELECT COUNT(*) FROM video_files WHERE status = 'pending') as pending_videos,
    (SELECT COUNT(*) FROM video_processing_queue) as total_tasks,
    (SELECT COUNT(*) FROM video_processing_queue WHERE status = 'pending') as pending_tasks;
