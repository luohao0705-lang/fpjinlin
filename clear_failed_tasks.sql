-- 清理失败的任务，重置为待处理状态

-- 1. 重置所有失败的任务为pending
UPDATE video_processing_queue 
SET status = 'pending', error_message = NULL, retry_count = 0 
WHERE status = 'failed';

-- 2. 重置所有处理中的任务为pending（避免卡住）
UPDATE video_processing_queue 
SET status = 'pending', error_message = NULL, retry_count = 0 
WHERE status = 'processing';

-- 3. 查看清理结果
SELECT 
    status,
    COUNT(*) as count
FROM video_processing_queue 
GROUP BY status;
