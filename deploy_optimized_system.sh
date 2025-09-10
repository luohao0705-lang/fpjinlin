#!/bin/bash

echo "🚀 部署优化的视频录制系统"
echo "========================"

# 1. 检查系统状态
echo "1. 检查系统状态..."
php system_monitor.php

# 2. 测试严谨的视频处理系统
echo "2. 测试严谨的视频处理系统..."
php test_strict_processor.php

# 3. 清理旧的任务和临时文件
echo "3. 清理系统..."
php -r "
require_once 'config/database.php';
\$db = new Database();

// 清理失败的任务
\$db->query(\"UPDATE video_processing_queue SET status = 'pending', error_message = NULL WHERE status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)\");

// 清理临时文件
\$tempDirs = glob('/tmp/record_*');
foreach (\$tempDirs as \$dir) {
    if (is_dir(\$dir)) {
        exec(\"rm -rf \$dir\");
    }
}

echo \"✅ 系统清理完成\n\";
"

# 4. 设置定时任务（可选）
echo "4. 设置系统监控定时任务..."
echo "*/5 * * * * cd /www/wwwroot/www.hsh6.com && php system_monitor.php >> /var/log/video_system_monitor.log 2>&1" | crontab -

echo "✅ 优化系统部署完成！"
echo ""
echo "📋 使用说明："
echo "1. 运行 'php system_monitor.php' 查看系统状态"
echo "2. 运行 'php test_strict_processor.php' 测试录制功能"
echo "3. 系统会自动监控CPU、内存、磁盘使用情况"
echo "4. 录制任务会严格按顺序执行，避免资源冲突"
echo ""
