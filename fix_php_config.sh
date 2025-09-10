#!/bin/bash
# 修复PHP配置脚本

echo "🔧 修复PHP配置"
echo "============="

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

# 创建临时配置文件
cat > /tmp/php_config_additions.ini << 'EOF'
; 轻量级录制系统优化配置
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 100M
upload_max_filesize = 100M
max_file_uploads = 20
default_socket_timeout = 60
max_input_vars = 3000
max_input_nesting_level = 64
EOF

# 检查配置是否已存在
if grep -q "轻量级录制系统优化配置" "$PHP_INI_PATH"; then
    echo "⚠️ 配置已存在，跳过添加"
else
    # 添加配置到文件末尾
    cat /tmp/php_config_additions.ini >> "$PHP_INI_PATH"
    echo "✅ 已添加优化配置"
fi

# 清理临时文件
rm -f /tmp/php_config_additions.ini

echo "🎉 PHP配置修复完成！"
echo "请重启Web服务器使配置生效"