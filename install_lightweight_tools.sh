#!/bin/bash
# 安装轻量级录制工具

echo "🔧 安装轻量级录制工具"
echo "==================="

# 检查系统类型
if [ -f /etc/redhat-release ]; then
    OS="centos"
elif [ -f /etc/debian_version ]; then
    OS="debian"
else
    echo "❌ 不支持的操作系统"
    exit 1
fi

echo "检测到操作系统: $OS"

# 安装wget（通常已安装）
echo "1. 检查wget..."
if ! command -v wget &> /dev/null; then
    echo "安装wget..."
    if [ "$OS" = "centos" ]; then
        yum install -y wget
    else
        apt-get update && apt-get install -y wget
    fi
else
    echo "✅ wget已安装"
fi

# 安装yt-dlp
echo "2. 安装yt-dlp..."
if ! command -v yt-dlp &> /dev/null; then
    echo "下载yt-dlp..."
    wget -O /usr/local/bin/yt-dlp https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp
    chmod +x /usr/local/bin/yt-dlp
    echo "✅ yt-dlp安装完成"
else
    echo "✅ yt-dlp已安装"
fi

# 检查ffmpeg
echo "3. 检查ffmpeg..."
if ! command -v ffmpeg &> /dev/null; then
    echo "安装ffmpeg..."
    if [ "$OS" = "centos" ]; then
        yum install -y epel-release
        yum install -y ffmpeg
    else
        apt-get update && apt-get install -y ffmpeg
    fi
    echo "✅ ffmpeg安装完成"
else
    echo "✅ ffmpeg已安装"
fi

# 测试工具
echo "4. 测试工具..."
echo "wget版本: $(wget --version | head -n1)"
echo "yt-dlp版本: $(yt-dlp --version)"
echo "ffmpeg版本: $(ffmpeg -version | head -n1)"

echo "🎉 安装完成！"
echo "现在可以使用轻量级录制器了"
