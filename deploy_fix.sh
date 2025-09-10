#!/bin/bash

# 复盘精灵数据库修复脚本
# 在Linux服务器上执行

echo "🔧 开始修复数据库结构问题..."
echo "=================================="

# 设置变量
DB_HOST="127.0.0.1"
DB_NAME="fupan_jingling"
DB_USER="webapp"
DB_PASS="password123"

# 检查MySQL是否运行
if ! systemctl is-active --quiet mysql; then
    echo "❌ MySQL服务未运行，请先启动MySQL服务"
    exit 1
fi

echo "✅ MySQL服务正在运行"

# 执行数据库修复
echo "📝 执行数据库结构修复..."
mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < fix_database_issues.sql

if [ $? -eq 0 ]; then
    echo "✅ 数据库结构修复成功"
else
    echo "❌ 数据库结构修复失败"
    exit 1
fi

# 检查表结构
echo "🔍 检查表结构..."
mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME -e "
SELECT 
    'video_analysis_orders' as table_name, 
    COUNT(*) as column_count 
FROM information_schema.columns 
WHERE table_schema = '$DB_NAME' AND table_name = 'video_analysis_orders'
UNION ALL
SELECT 
    'video_files' as table_name, 
    COUNT(*) as column_count 
FROM information_schema.columns 
WHERE table_schema = '$DB_NAME' AND table_name = 'video_files'
UNION ALL
SELECT 
    'video_processing_queue' as table_name, 
    COUNT(*) as column_count 
FROM information_schema.columns 
WHERE table_schema = '$DB_NAME' AND table_name = 'video_processing_queue';
"

# 测试修复结果
echo "🧪 测试修复结果..."
cd /www/wwwroot/www.hsh6.com

echo "1. 测试数据库连接..."
php -r "
require_once 'config/database.php';
try {
    \$db = new Database();
    echo '✅ 数据库连接成功\n';
} catch (Exception \$e) {
    echo '❌ 数据库连接失败: ' . \$e->getMessage() . '\n';
    exit(1);
}
"

echo "2. 测试表结构..."
php -r "
require_once 'config/database.php';
try {
    \$db = new Database();
    \$columns = \$db->fetchAll('SHOW COLUMNS FROM video_analysis_orders');
    echo '✅ video_analysis_orders表字段数: ' . count(\$columns) . '\n';
    
    \$hasFlvUrl = false;
    foreach (\$columns as \$column) {
        if (\$column['Field'] === 'self_flv_url') {
            \$hasFlvUrl = true;
            break;
        }
    }
    
    if (\$hasFlvUrl) {
        echo '✅ self_flv_url字段存在\n';
    } else {
        echo '❌ self_flv_url字段不存在\n';
    }
} catch (Exception \$e) {
    echo '❌ 检查表结构失败: ' . \$e->getMessage() . '\n';
}
"

echo "3. 测试调试脚本..."
php debug_task_status.php

echo "4. 测试监控脚本..."
php monitor_tasks.php

echo "5. 测试分析脚本..."
php test_start_analysis.php

echo "🎉 修复完成！"
echo "=================================="
echo "如果所有测试都通过，说明数据库问题已修复"
echo "如果还有问题，请检查错误信息并手动调整"
