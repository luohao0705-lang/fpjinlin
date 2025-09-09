# Linux系统FFmpeg安装指南

## 🐧 在Linux系统上安装FFmpeg

### 方法1：使用包管理器安装（推荐）

#### Ubuntu/Debian系统
```bash
# 更新包列表
sudo apt update

# 安装FFmpeg
sudo apt install ffmpeg

# 验证安装
ffmpeg -version
```

#### CentOS/RHEL系统
```bash
# 安装EPEL仓库
sudo yum install epel-release

# 安装FFmpeg
sudo yum install ffmpeg

# 或者使用dnf（较新版本）
sudo dnf install ffmpeg

# 验证安装
ffmpeg -version
```

#### Fedora系统
```bash
# 安装FFmpeg
sudo dnf install ffmpeg

# 验证安装
ffmpeg -version
```

### 方法2：从源码编译安装

```bash
# 安装依赖
sudo apt install -y autoconf automake build-essential cmake git-core libass-dev libfreetype6-dev libgnutls28-dev libmp3lame-dev libsdl2-dev libtool libva-dev libvdpau-dev libvorbis-dev libxcb1-dev libxcb-shm0-dev libxcb-xfixes0-dev meson ninja-build pkg-config texinfo wget yasm zlib1g-dev

# 下载FFmpeg源码
cd /tmp
wget https://ffmpeg.org/releases/ffmpeg-6.0.tar.xz
tar -xf ffmpeg-6.0.tar.xz
cd ffmpeg-6.0

# 配置编译选项
./configure --enable-gpl --enable-libass --enable-libfreetype --enable-libmp3lame --enable-libvorbis --enable-libx264 --enable-libx265 --enable-libvpx --enable-libfdk-aac --enable-libopus --enable-libtheora --enable-libvorbis --enable-libxvid --enable-libx264 --enable-libx265 --enable-libvpx --enable-libfdk-aac --enable-libopus --enable-libtheora --enable-libvorbis --enable-libxvid

# 编译安装
make -j$(nproc)
sudo make install

# 验证安装
ffmpeg -version
```

### 方法3：使用Snap安装

```bash
# 安装FFmpeg
sudo snap install ffmpeg

# 验证安装
ffmpeg -version
```

## 🔍 验证安装

安装完成后，运行以下命令验证：

```bash
# 检查FFmpeg版本
ffmpeg -version

# 检查FFmpeg位置
which ffmpeg

# 检查FFmpeg支持的格式
ffmpeg -formats
```

## 🛠️ 常见问题解决

### 1. 找不到FFmpeg命令
```bash
# 检查PATH环境变量
echo $PATH

# 手动添加到PATH（临时）
export PATH=$PATH:/usr/local/bin

# 永久添加到PATH
echo 'export PATH=$PATH:/usr/local/bin' >> ~/.bashrc
source ~/.bashrc
```

### 2. 权限问题
```bash
# 确保FFmpeg有执行权限
sudo chmod +x /usr/bin/ffmpeg
# 或
sudo chmod +x /usr/local/bin/ffmpeg
```

### 3. 依赖库缺失
```bash
# 安装常用依赖库
sudo apt install -y libavcodec-dev libavformat-dev libavutil-dev libswscale-dev libavresample-dev
```

## 📋 系统要求

- **操作系统**: Linux (Ubuntu 18.04+, CentOS 7+, Fedora 30+)
- **内存**: 至少2GB RAM
- **存储**: 至少1GB可用空间
- **网络**: 需要网络连接下载依赖

## 🚀 测试FFmpeg功能

安装完成后，可以测试FFmpeg的录制功能：

```bash
# 测试录制网络流
ffmpeg -i "http://example.com/stream.flv" -t 10 -c copy test_output.mp4

# 测试转码功能
ffmpeg -i input.mp4 -c:v libx264 -c:a aac output.mp4
```

## 📞 技术支持

如果遇到安装问题，可以：

1. 查看系统日志：`journalctl -u ffmpeg`
2. 检查FFmpeg日志：`ffmpeg -v debug`
3. 联系系统管理员
4. 查看FFmpeg官方文档：https://ffmpeg.org/documentation.html

## ✅ 安装完成检查

安装完成后，在复盘精灵系统中：

1. 进入后台订单详情页面
2. 点击"系统检查"按钮
3. 查看FFmpeg状态是否显示"已安装"
4. 点击"测试录制"按钮测试功能

如果FFmpeg状态显示"已安装"，说明安装成功！
