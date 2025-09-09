-- 安全的video_files表修复方法
-- 不删除数据，只修改字段定义

-- 方法1: 尝试直接修改（最安全）
ALTER TABLE `video_files` 
MODIFY COLUMN `status` ENUM('pending', 'downloading', 'processing', 'completed', 'failed', 'stopped') DEFAULT 'pending' COMMENT '处理状态';

-- 如果上面失败，说明可能有数据冲突，需要先清理数据
-- 查看是否有不符合新ENUM值的数据
-- SELECT DISTINCT status FROM video_files WHERE status NOT IN ('pending', 'downloading', 'processing', 'completed', 'failed', 'stopped');

-- 如果有其他值，先更新为有效值
-- UPDATE video_files SET status = 'pending' WHERE status NOT IN ('pending', 'downloading', 'processing', 'completed', 'failed', 'stopped');
