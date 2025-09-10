#!/bin/bash
# 手动修复PHP配置脚本

echo "🔧 手动修复PHP配置"
echo "================="

# 找到PHP配置文件
PHP_INI_PATH="/www/server/php/82/etc/php.ini"

if [ ! -f "$PHP_INI_PATH" ]; then
    echo "❌ 找不到PHP配置文件: $PHP_INI_PATH"
    exit 1
fi

echo "找到PHP配置文件: $PHP_INI_PATH"

# 备份原配置文件
cp "$PHP_INI_PATH" "$PHP_INI_PATH.backup.$(date +%Y%m%d_%H%M%S)"
echo "✅ 已备份原配置文件"

# 使用sed直接修改配置
echo "修改内存限制..."
sed -i 's/memory_limit = 128M/memory_limit = 512M/g' "$PHP_INI_PATH"

echo "修改最大执行时间..."
sed -i 's/max_execution_time = 0/max_execution_time = 300/g' "$PHP_INI_PATH"

echo "修改最大输入时间..."
sed -i 's/max_input_time = -1/max_input_time = 300/g' "$PHP_INI_PATH"

echo "修改POST最大大小..."
sed -i 's/post_max_size = 50M/post_max_size = 100M/g' "$PHP_INI_PATH"

echo "修改上传最大文件大小..."
sed -i 's/upload_max_filesize = 50M/upload_max_filesize = 100M/g' "$PHP_INI_PATH"

# 检查修改结果
echo "检查修改结果:"
grep "memory_limit" "$PHP_INI_PATH" | head -1
grep "max_execution_time" "$PHP_INI_PATH" | head -1
grep "max_input_time" "$PHP_INI_PATH" | head -1
grep "post_max_size" "$PHP_INI_PATH" | head -1
grep "upload_max_filesize" "$PHP_INI_PATH" | head -1

echo "🎉 PHP配置修复完成！"
echo "请重启Web服务器使配置生效"
