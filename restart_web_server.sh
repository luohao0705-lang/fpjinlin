#!/bin/bash
# Web服务器重启脚本

echo "🔄 重启Web服务器"
echo "==============="

# 检测Web服务器类型
if systemctl is-active --quiet nginx; then
    echo "检测到Nginx服务器"
    systemctl restart nginx
    echo "✅ Nginx已重启"
elif systemctl is-active --quiet httpd; then
    echo "检测到Apache服务器"
    systemctl restart httpd
    echo "✅ Apache已重启"
elif systemctl is-active --quiet apache2; then
    echo "检测到Apache2服务器"
    systemctl restart apache2
    echo "✅ Apache2已重启"
else
    echo "⚠️ 未检测到常见的Web服务器"
    echo "请手动重启Web服务器"
fi

# 检查PHP-FPM
if systemctl is-active --quiet php-fpm; then
    echo "检测到PHP-FPM"
    systemctl restart php-fpm
    echo "✅ PHP-FPM已重启"
fi

echo "🎉 Web服务器重启完成！"