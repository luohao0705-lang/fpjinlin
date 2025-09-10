#!/bin/bash
# 一键安装和配置轻量级录制系统

echo "🚀 安装轻量级录制系统"
echo "===================="

# 检查是否为root用户
if [ "$EUID" -ne 0 ]; then
    echo "❌ 请使用root用户运行此脚本"
    echo "使用: sudo $0"
    exit 1
fi

# 检测操作系统
if [ -f /etc/redhat-release ]; then
    OS="centos"
    PKG_MANAGER="yum"
elif [ -f /etc/debian_version ]; then
    OS="debian"
    PKG_MANAGER="apt-get"
else
    echo "❌ 不支持的操作系统"
    exit 1
fi

echo "检测到操作系统: $OS"

# 1. 更新包管理器
echo "1. 更新包管理器..."
if [ "$OS" = "centos" ]; then
    yum update -y
    yum install -y epel-release
else
    apt-get update -y
fi

# 2. 安装必需工具
echo "2. 安装必需工具..."
if [ "$OS" = "centos" ]; then
    yum install -y wget ffmpeg ffmpeg-devel
else
    apt-get install -y wget ffmpeg
fi

# 3. 安装Python和pip（用于yt-dlp）
echo "3. 安装Python和pip..."
if [ "$OS" = "centos" ]; then
    yum install -y python3 python3-pip
else
    apt-get install -y python3 python3-pip
fi

# 4. 安装yt-dlp
echo "4. 安装yt-dlp..."
pip3 install yt-dlp

# 5. 创建符号链接
echo "5. 创建符号链接..."
ln -sf /usr/local/bin/yt-dlp /usr/bin/yt-dlp 2>/dev/null || true

# 6. 测试工具
echo "6. 测试工具..."
echo "wget版本: $(wget --version | head -n1)"
echo "ffmpeg版本: $(ffmpeg -version | head -n1)"
echo "yt-dlp版本: $(yt-dlp --version)"

# 7. 设置权限
echo "7. 设置权限..."
chmod +x /usr/local/bin/yt-dlp 2>/dev/null || true

# 8. 创建临时目录
echo "8. 创建临时目录..."
mkdir -p /tmp/video_recording
chmod 777 /tmp/video_recording

# 9. 检查PHP配置
echo "9. 检查PHP配置..."
if command -v php &> /dev/null; then
    echo "PHP版本: $(php -v | head -n1)"
    
    # 检查PHP配置
    echo "内存限制: $(php -r 'echo ini_get("memory_limit");')"
    echo "最大执行时间: $(php -r 'echo ini_get("max_execution_time");')"
    
    # 建议的PHP配置
    echo "建议的PHP配置:"
    echo "- memory_limit = 512M"
    echo "- max_execution_time = 300"
    echo "- max_input_time = 300"
    echo "- post_max_size = 100M"
    echo "- upload_max_filesize = 100M"
else
    echo "❌ PHP未安装"
fi

echo "🎉 安装完成！"
echo "现在可以运行: php check_system_requirements.php"
