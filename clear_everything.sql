-- 彻底清理所有测试数据
-- 删除所有视频相关的数据

-- 1. 删除所有视频文件记录
DELETE FROM video_files;

-- 2. 删除所有处理任务
DELETE FROM video_processing_queue;

-- 3. 删除所有视频分析订单
DELETE FROM video_analysis_orders;

-- 4. 删除所有视频分析结果
DELETE FROM video_analysis_results;

-- 5. 删除所有视频片段
DELETE FROM video_segments;

-- 6. 删除所有视频转录
DELETE FROM video_transcripts;

-- 7. 查看清理结果
SELECT 
    (SELECT COUNT(*) FROM video_files) as video_files,
    (SELECT COUNT(*) FROM video_processing_queue) as processing_queue,
    (SELECT COUNT(*) FROM video_analysis_orders) as analysis_orders,
    (SELECT COUNT(*) FROM video_analysis_results) as analysis_results;
